/**
 * AssessPay boot — runs after module-bridge establishes identity via DEORIS SSO.
 *
 * Mirrors EnrollEase's enrollease.js pattern exactly:
 * 1. module:ready fires with user identity from DEORIS
 * 2. POST identity to /sso/redirect → AssessPay creates session → redirect to dashboard
 */
if (!window.__ASSESMENT_LOADED__) {
    window.__ASSESMENT_LOADED__ = true;

    function showInitError(message) {
        var el = document.getElementById("assesspay-loader-error");
        var msg = document.getElementById("assesspay-loader-msg");
        if (msg) msg.textContent = "Sign-in failed";
        if (el) {
            el.style.display = "block";
            el.textContent = message;
        }
    }

    function bootFromPortalUser(detail) {
        var user     = (detail && detail.user) || window.PORTAL_USER || null;
        var token    = (detail && detail.token) || window.SSO_TOKEN || null;
        var embedded = (detail && detail.embedded) ? "1" : "0";

        var msg = document.getElementById("assesspay-loader-msg");
        if (msg) msg.textContent = "Opening your dashboard…";

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (!csrfMeta) { showInitError("CSRF token missing."); return; }
        if (!token && !(user && user.id)) { showInitError("SSO identity missing."); return; }

        var url = user && user.id ? "/sso/redirect" : "/sso/exchange";
        var payload = user && user.id ? {
            id: user.id,
            name: user.name || "",
            email: user.email || "",
            role: user.role || "student",
            embedded: embedded
        } : {
            token: token,
            embedded: embedded === "1",
        };

        fetch(url, {
            method:      "POST",
            credentials: "same-origin",
            headers:     {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfMeta.getAttribute("content"),
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(payload),
        }).then(function (res) {
            if (!res.ok) throw new Error("SSO exchange failed: " + res.status);
            return res.json().catch(function () { throw new Error("Invalid JSON"); });
        }).then(function (json) {
            if (!json || !json.redirect) {
                showInitError("SSO succeeded but no redirect provided.");
                return;
            }
            // Navigate to the dashboard. The portal does not watch iframe URL
            // changes, so this navigation is safe and does not cause a reload loop.
            window.location.href = json.redirect;
        }).catch(function (err) {
            console.error("[assesment] SSO flow failed:", err);
            showInitError("Authentication failed during exchange.");
        });
    }

    window.addEventListener("module:ready", function (event) {
        bootFromPortalUser(event.detail || {});
    });

    window.addEventListener("module:error", function (event) {
        var detail = event.detail || {};
        var text =
            detail.error === "sso_timeout"
                ? "Sign-in timed out. Return to DEORIS and open AssessPay again."
                : "Authentication failed: " + (detail.error || "unknown error");
        showInitError(text);
    });

    // Bridge may have already resolved before this listener attached
    if (window.__DEORIS_MODULE_READY_DETAIL__ || (window.PORTAL_USER && window.PORTAL_USER.id)) {
        bootFromPortalUser(
            window.__DEORIS_MODULE_READY_DETAIL__ || { user: window.PORTAL_USER, embedded: true }
        );
    } else if (window.__DEORIS_MODULE_ERROR_DETAIL__) {
        showInitError("Authentication failed: " + (window.__DEORIS_MODULE_ERROR_DETAIL__.error || "Unknown error"));
    }
}
