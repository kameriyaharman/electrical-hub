package com.electricalhub.app;

import android.Manifest;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.Build;

import androidx.annotation.NonNull;
import androidx.core.app.NotificationCompat;
import androidx.core.app.NotificationManagerCompat;
import androidx.core.content.ContextCompat;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.ExistingWorkPolicy;
import androidx.work.OneTimeWorkRequest;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.TimeUnit;

/** Checks the server for new admin notifications (about every 15 minutes) and shows them on the phone. */
public class NotifyWorker extends Worker {

    static final String PREFS = "eh_notify";
    static final String CHANNEL = "eh_updates";

    public NotifyWorker(@NonNull Context context, @NonNull WorkerParameters params) {
        super(context, params);
    }

    static void schedule(Context ctx) {
        PeriodicWorkRequest periodic = new PeriodicWorkRequest.Builder(NotifyWorker.class, 15, TimeUnit.MINUTES).build();
        WorkManager.getInstance(ctx).enqueueUniquePeriodicWork("eh_notify", ExistingPeriodicWorkPolicy.KEEP, periodic);
    }

    static void checkNow(Context ctx) {
        WorkManager.getInstance(ctx).enqueueUniqueWork("eh_notify_now", ExistingWorkPolicy.REPLACE,
                new OneTimeWorkRequest.Builder(NotifyWorker.class).build());
    }

    @NonNull
    @Override
    public Result doWork() {
        Context ctx = getApplicationContext();
        SharedPreferences sp = ctx.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        String token = sp.getString("token", "");
        int city = sp.getInt("city_id", 0);
        int since = sp.getInt("last_id", -1);
        HttpURLConnection c = null;
        try {
            String url = BuildConfig.API_BASE + "?r=notifications/feed&city_id=" + city + "&since=" + Math.max(since, 0);
            c = (HttpURLConnection) new URL(url).openConnection();
            c.setConnectTimeout(15000);
            c.setReadTimeout(15000);
            if (!token.isEmpty()) c.setRequestProperty("X-Auth-Token", token);
            if (c.getResponseCode() != 200) return Result.success();
            StringBuilder sb = new StringBuilder();
            try (BufferedReader r = new BufferedReader(new InputStreamReader(c.getInputStream(), "UTF-8"))) {
                String line;
                while ((line = r.readLine()) != null) sb.append(line);
            }
            JSONObject res = new JSONObject(sb.toString());
            int latest = res.optInt("latest_id", 0);
            // First run: remember where we are, don't show old notifications
            if (since >= 0) {
                JSONArray items = res.optJSONArray("items");
                if (items != null) {
                    for (int i = items.length() - 1; i >= 0; i--) {
                        JSONObject n = items.getJSONObject(i);
                        show(ctx, n.optInt("id"), n.optString("title"), n.optString("body"), n.optInt("shop_id", 0));
                    }
                }
            }
            sp.edit().putInt("last_id", Math.max(latest, Math.max(since, 0))).apply();
        } catch (Exception ignored) {
            return Result.success();
        } finally {
            if (c != null) c.disconnect();
        }
        return Result.success();
    }

    static void ensureChannel(Context ctx) {
        if (Build.VERSION.SDK_INT >= 26) {
            NotificationManager nm = ctx.getSystemService(NotificationManager.class);
            if (nm != null && nm.getNotificationChannel(CHANNEL) == null) {
                nm.createNotificationChannel(new NotificationChannel(CHANNEL, "Offers & updates", NotificationManager.IMPORTANCE_DEFAULT));
            }
        }
    }

    private static void show(Context ctx, int id, String title, String body, int shopId) {
        if (Build.VERSION.SDK_INT >= 33 && ContextCompat.checkSelfPermission(ctx, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) return;
        ensureChannel(ctx);
        Intent open = new Intent(ctx, MainActivity.class);
        open.putExtra("route", shopId > 0 ? "#/shop/" + shopId : "#/notifications");
        open.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent pi = PendingIntent.getActivity(ctx, id, open, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
        NotificationCompat.Builder b = new NotificationCompat.Builder(ctx, CHANNEL)
                .setSmallIcon(R.drawable.ic_stat_bolt)
                .setColor(0xFFFF7A00)
                .setContentTitle(title)
                .setContentText(body)
                .setStyle(new NotificationCompat.BigTextStyle().bigText(body))
                .setAutoCancel(true)
                .setContentIntent(pi);
        try { NotificationManagerCompat.from(ctx).notify(1000 + id, b.build()); } catch (SecurityException ignored) { }
    }
}
