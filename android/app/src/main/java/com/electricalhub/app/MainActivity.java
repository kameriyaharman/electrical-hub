package com.electricalhub.app;

import android.Manifest;
import android.annotation.SuppressLint;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.MediaStore;
import android.view.View;
import android.webkit.GeolocationPermissions;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.core.content.FileProvider;
import androidx.webkit.WebViewAssetLoader;

import java.io.File;
import java.io.IOException;

public class MainActivity extends AppCompatActivity {

    private static final String START_URL = "https://appassets.androidplatform.net/assets/www/index.html";
    private static final int REQ_CAMERA = 11;
    private static final int REQ_LOCATION = 12;
    private static final int REQ_FILE = 21;

    private WebView web;
    private WebViewAssetLoader assetLoader;
    private ValueCallback<Uri[]> fileCallback;
    private Uri cameraUri;
    private String pendingLocationCb;
    private final Handler main = new Handler(Looper.getMainLooper());

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().setStatusBarColor(Color.parseColor("#121212"));
        getWindow().setNavigationBarColor(Color.parseColor("#121212"));

        web = new WebView(this);
        web.setBackgroundColor(Color.parseColor("#F6F4F2"));
        setContentView(web);

        assetLoader = new WebViewAssetLoader.Builder()
                .addPathHandler("/assets/", new WebViewAssetLoader.AssetsPathHandler(this))
                .build();

        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setDatabaseEnabled(true);
        s.setGeolocationEnabled(true);
        s.setAllowFileAccess(false);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setUserAgentString(s.getUserAgentString() + " ElectricalHubApp/1.0");

        web.addJavascriptInterface(new Bridge(), "Android");
        web.setWebViewClient(new WebViewClient() {
            @Override
            public WebResourceResponse shouldInterceptRequest(WebView view, WebResourceRequest request) {
                return assetLoader.shouldInterceptRequest(request.getUrl());
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri u = request.getUrl();
                if ("appassets.androidplatform.net".equals(u.getHost())) return false;
                openExternal(u.toString());
                return true;
            }
        });
        web.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
                callback.invoke(origin, hasLocationPermission(), false);
            }

            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (fileCallback != null) fileCallback.onReceiveValue(null);
                fileCallback = callback;
                if (params.isCaptureEnabled()) {
                    // Live shop photos: camera only, never the gallery
                    if (ContextCompat.checkSelfPermission(MainActivity.this, Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
                        ActivityCompat.requestPermissions(MainActivity.this, new String[]{Manifest.permission.CAMERA}, REQ_CAMERA);
                    } else {
                        launchCamera();
                    }
                } else {
                    try {
                        Intent i = params.createIntent();
                        i.setType("*/*");
                        i.putExtra(Intent.EXTRA_MIME_TYPES, new String[]{"image/*", "application/pdf"});
                        startActivityForResult(Intent.createChooser(i, "Upload list"), REQ_FILE);
                    } catch (ActivityNotFoundException e) {
                        fileCallback.onReceiveValue(null);
                        fileCallback = null;
                    }
                }
                return true;
            }
        });

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                web.evaluateJavascript("(window.EH && window.EH.back()) ? 'y' : 'n'", value -> {
                    if (value == null || !value.contains("y")) finish();
                });
            }
        });

        if (!hasLocationPermission()) {
            ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION}, REQ_LOCATION);
        }

        if (savedInstanceState != null) web.restoreState(savedInstanceState);
        else web.loadUrl(START_URL);
    }

    @Override
    protected void onSaveInstanceState(@NonNull Bundle outState) {
        super.onSaveInstanceState(outState);
        web.saveState(outState);
    }

    // ---------- camera ----------
    private void launchCamera() {
        try {
            File dir = new File(getCacheDir(), "camera");
            if (!dir.exists()) dir.mkdirs();
            File f = File.createTempFile("live_", ".jpg", dir);
            cameraUri = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", f);
            Intent i = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
            i.putExtra(MediaStore.EXTRA_OUTPUT, cameraUri);
            i.addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION | Intent.FLAG_GRANT_READ_URI_PERMISSION);
            startActivityForResult(i, REQ_CAMERA);
        } catch (IOException | ActivityNotFoundException e) {
            Toast.makeText(this, "Camera not available", Toast.LENGTH_SHORT).show();
            if (fileCallback != null) fileCallback.onReceiveValue(null);
            fileCallback = null;
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (fileCallback == null) return;
        Uri[] result = null;
        if (resultCode == RESULT_OK) {
            if (requestCode == REQ_CAMERA && cameraUri != null) result = new Uri[]{cameraUri};
            else if (requestCode == REQ_FILE && data != null) {
                if (data.getClipData() != null && data.getClipData().getItemCount() > 0) result = new Uri[]{data.getClipData().getItemAt(0).getUri()};
                else if (data.getData() != null) result = new Uri[]{data.getData()};
            }
        }
        fileCallback.onReceiveValue(result);
        fileCallback = null;
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        boolean granted = grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED;
        if (requestCode == REQ_CAMERA) {
            if (granted) launchCamera();
            else {
                Toast.makeText(this, "Camera permission is needed for live shop photos", Toast.LENGTH_LONG).show();
                if (fileCallback != null) fileCallback.onReceiveValue(null);
                fileCallback = null;
            }
        } else if (requestCode == REQ_LOCATION && pendingLocationCb != null) {
            String cb = pendingLocationCb;
            pendingLocationCb = null;
            if (granted) fetchLocation(cb); else sendLocation(cb, null);
        }
    }

    // ---------- location ----------
    private boolean hasLocationPermission() {
        return ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
                || ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED;
    }

    @SuppressLint("MissingPermission")
    private void fetchLocation(final String cb) {
        final LocationManager lm = (LocationManager) getSystemService(LOCATION_SERVICE);
        if (lm == null) { sendLocation(cb, null); return; }
        Location best = null;
        for (String p : lm.getProviders(true)) {
            try {
                Location l = lm.getLastKnownLocation(p);
                if (l != null && (best == null || l.getTime() > best.getTime())) best = l;
            } catch (SecurityException ignored) { }
        }
        final boolean fresh = best != null && System.currentTimeMillis() - best.getTime() < 2 * 60 * 1000;
        if (fresh) { sendLocation(cb, best); return; }
        final Location fallback = best;
        final boolean[] done = {false};
        final LocationListener listener = new LocationListener() {
            @Override
            public void onLocationChanged(@NonNull Location location) {
                if (done[0]) return;
                done[0] = true;
                lm.removeUpdates(this);
                sendLocation(cb, location);
            }
            @Override public void onProviderEnabled(@NonNull String provider) { }
            @Override public void onProviderDisabled(@NonNull String provider) { }
            @Override public void onStatusChanged(String provider, int status, Bundle extras) { }
        };
        boolean requested = false;
        for (String p : new String[]{LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER}) {
            try {
                if (lm.isProviderEnabled(p)) { lm.requestLocationUpdates(p, 0, 0, listener, Looper.getMainLooper()); requested = true; }
            } catch (Exception ignored) { }
        }
        if (!requested) {
            Toast.makeText(this, "Please turn on Location (GPS)", Toast.LENGTH_SHORT).show();
            sendLocation(cb, fallback);
            return;
        }
        main.postDelayed(() -> {
            if (!done[0]) { done[0] = true; lm.removeUpdates(listener); sendLocation(cb, fallback); }
        }, 12000);
    }

    private void sendLocation(String cb, Location l) {
        final String js = l == null
                ? "window['" + cb + "'] && window['" + cb + "'](null,null)"
                : "window['" + cb + "'] && window['" + cb + "'](" + l.getLatitude() + "," + l.getLongitude() + ")";
        main.post(() -> web.evaluateJavascript(js, null));
    }

    // ---------- external apps ----------
    private void openExternal(String url) {
        try {
            Intent i;
            if (url.startsWith("tel:")) i = new Intent(Intent.ACTION_DIAL, Uri.parse(url));
            else if (url.startsWith("upi:")) i = Intent.createChooser(new Intent(Intent.ACTION_VIEW, Uri.parse(url)), "Pay with");
            else i = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
            startActivity(i);
        } catch (ActivityNotFoundException e) {
            Toast.makeText(this, url.startsWith("upi:") ? "No UPI app found" : "No app found to open this", Toast.LENGTH_SHORT).show();
        }
    }

    private class Bridge {
        @JavascriptInterface
        public String apiBase() { return BuildConfig.API_BASE; }

        @JavascriptInterface
        public void getLocation(final String cb) {
            main.post(() -> {
                if (hasLocationPermission()) fetchLocation(cb);
                else {
                    pendingLocationCb = cb;
                    ActivityCompat.requestPermissions(MainActivity.this, new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION}, REQ_LOCATION);
                }
            });
        }

        @JavascriptInterface
        public void share(final String text) {
            main.post(() -> {
                Intent i = new Intent(Intent.ACTION_SEND);
                i.setType("text/plain");
                i.putExtra(Intent.EXTRA_TEXT, text);
                startActivity(Intent.createChooser(i, "Share"));
            });
        }

        @JavascriptInterface
        public void openUrl(final String url) { main.post(() -> openExternal(url)); }
    }
}
