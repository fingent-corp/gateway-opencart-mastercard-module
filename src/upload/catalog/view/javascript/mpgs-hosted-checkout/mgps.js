const scriptTag = document.querySelector('script[src*="mgps.js"]');
const jsonData = scriptTag.getAttribute('data-sessionid');

// Keys written to browser sessionStorage by the MPGS Checkout.js library.
// All entries are removed before each new checkout session is initialised so
// a stale session from a previous payment cannot be reused by the library.
const sessionKeysToClear = ['HostedCheckout_sessionId'];

function cleanupBrowserSession() {
    for (const sessionKey of sessionKeysToClear) {
        if (sessionStorage.getItem(sessionKey) !== null) {
            sessionStorage.removeItem(sessionKey);
        }
    }
}

function errorCallback(error) {
    let err = JSON.stringify(error);
    console.error(err);
    alert('Error: ' + JSON.stringify(error));
}

function loadScript(src, onloadCallback) {
    const script = document.createElement('script');
    script.src = src;
    script.onload = () => {
        onloadCallback();
    };
    document.head.appendChild(script);
}

function clearEmbedTarget() {
   
    const embedTarget = document.getElementById('embed-target');
    embedTarget.innerHTML = ''; 
}

