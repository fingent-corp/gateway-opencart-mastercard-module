var scriptTag = document.querySelector('script[src*="mgps.js"]');
var jsonData = scriptTag.getAttribute('data-sessionid');

var sessionKeysToClear = [];
function cleanupBrowserSession() {
    var sessionKey, i;
    for (i = 0; i < sessionKeysToClear.length; i++) {
        sessionKey = sessionKeysToClear[i];
        if (sessionStorage.key(sessionKey)) {
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
    var script = document.createElement('script');
    script.src = src;
    script.onload = function() {
        checkoutLoaded = true;
        onloadCallback();
    };
    document.head.appendChild(script);
}

function clearEmbedTarget() {
   
    var embedTarget = document.getElementById('embed-target');
    embedTarget.innerHTML = ''; 
}

