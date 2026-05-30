/**
 * AssessPay module-side SSO bridge.
 *
 * Mirrors the EnrollEase pattern exactly:
 * 1. Portal iframe sends single-use token via postMessage
 * 2. This script exchanges the token directly with DEORIS (/api/sso/exchange)
 * 3. On success, fires module:ready with user identity
 * 4. assesment.js catches module:ready and POSTs identity to /sso/redirect
 *    which creates the AssessPay session and redirects to the dashboard
 *
 * No server-to-server call from AssessPay to DEORIS.
 */
(function () {
  "use strict";

  if (window.__DEORIS_MODULE_BRIDGE_RUNNING__) return;
  window.__DEORIS_MODULE_BRIDGE_RUNNING__ = true;

  var PORTAL_ORIGIN = window.PORTAL_ORIGIN || "https://deoris.test";
  var SSO_TIMEOUT_MS = Number(window.SSO_TIMEOUT_MS || 15000);
  var requestId = String(Date.now()) + "-" + Math.random().toString(36).slice(2);
  var resolved = false;
  var timeoutId = null;

  window.PORTAL_ORIGIN = PORTAL_ORIGIN;
  window.SSO_TOKEN = null;
  window.PORTAL_USER = null;
  window.__DEORIS_MODULE_READY_DETAIL__ = null;
  window.__DEORIS_MODULE_ERROR_DETAIL__ = null;

  function isEmbedded() {
    try {
      return window.self !== window.top;
    } catch (e) {
      return true;
    }
  }

  function emit(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail: detail }));
  }

  function cleanupMemory() {
    window.SSO_TOKEN = null;
    window.PORTAL_USER = null;
    window.__DEORIS_MODULE_READY_DETAIL__ = null;
  }

  function finishError(error, code) {
    if (resolved) return;
    resolved = true;
    window.SSO_TOKEN = null;
    if (timeoutId) clearTimeout(timeoutId);

    console.error("[module-bridge] Auth error:", error);

    var detail = {
      success: false,
      error: error,
      code: code || "sso_failed",
      embedded: isEmbedded(),
      portalOrigin: PORTAL_ORIGIN,
    };
    window.__DEORIS_MODULE_ERROR_DETAIL__ = detail;
    emit("module:error", detail);
  }

  function finishReady(user) {
    if (resolved) return;
    resolved = true;
    window.SSO_TOKEN = null;
    window.PORTAL_USER = user;
    if (timeoutId) clearTimeout(timeoutId);

    console.log("[module-bridge] Ready:", user.email, "| role:", user.role);

    var detail = {
      success: true,
      user: user,
      embedded: isEmbedded(),
      portalOrigin: PORTAL_ORIGIN,
    };
    window.__DEORIS_MODULE_READY_DETAIL__ = detail;
    emit("module:ready", detail);
  }

  function revokePendingToken() {
    var token = window.SSO_TOKEN;
    window.SSO_TOKEN = null;
    if (!token || resolved) return;

    fetch(PORTAL_ORIGIN + "/api/v1/sso/revoke", {
      method: "POST",
      credentials: "include",
      keepalive: true,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ token: token }),
    }).catch(function () {});
  }

  /**
   * Exchange token directly with DEORIS — same as EnrollEase pattern.
   * DEORIS validates the Sanctum token and returns user identity.
   */
  function exchangeToken(token) {
    return fetch(PORTAL_ORIGIN + "/api/v1/sso/exchange", {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ token: token }),
    }).then(function (response) {
      return response.json()
        .catch(function () { return {}; })
        .then(function (body) {
          if (!response.ok || body.success === false) {
            throw new Error(body.error || ("http_" + response.status));
          }
          if (!body.user || !body.user.id) {
            throw new Error("missing_user");
          }
          return body.user;
        });
    });
  }

  window.addEventListener("message", function (event) {
    if (event.origin !== PORTAL_ORIGIN) return;
    if (!event.data || event.data.requestId !== requestId) return;

    if (event.data.type === "SSO_ERROR") {
      var portalErr = event.data.error || "sso_failed";
      if (portalErr === "unauthenticated") {
        portalErr = "DEORIS session expired — refresh the portal and log in again.";
      }
      finishError(portalErr, "portal_sso_error");
      return;
    }

    if (event.data.type !== "SSO_TOKEN") return;

    if (typeof event.data.token !== "string" || event.data.token.length === 0) {
      finishError("missing_sso_token", "missing_sso_token");
      return;
    }

    window.SSO_TOKEN = event.data.token;

    exchangeToken(window.SSO_TOKEN)
      .then(finishReady)
      .catch(function (error) {
        revokePendingToken();
        finishError(error.message || "exchange_failed", "exchange_failed");
      });
  });

  window.addEventListener("pagehide", function () {
    revokePendingToken();
    cleanupMemory();
  });

  window.addEventListener("beforeunload", function () {
    revokePendingToken();
    cleanupMemory();
  });

  if (!isEmbedded()) {
    finishError("Open AssessPay from the DEORIS portal.", "missing_iframe_context");
    return;
  }

  console.log("[module-bridge] Requesting SSO token from portal…");
  timeoutId = window.setTimeout(function () {
    finishError(
      "Sign-in timed out. Close this tab and open AssessPay again from DEORIS.",
      "sso_timeout"
    );
  }, SSO_TIMEOUT_MS);

  window.parent.postMessage(
    { type: "REQUEST_SSO", requestId: requestId },
    PORTAL_ORIGIN
  );
})();
