/**
 * Vercel Speed Insights Initialization
 * This script initializes Speed Insights for tracking web vitals
 */
(function() {
    // Initialize Speed Insights queue
    window.si = window.si || function () { 
        (window.siq = window.siq || []).push(arguments); 
    };
    
    // Load the Speed Insights script
    var script = document.createElement('script');
    script.defer = true;
    
    // Use production script URL
    script.src = 'https://va.vercel-scripts.com/v1/speed-insights/script.js';
    
    // Insert the script into the page
    var firstScript = document.getElementsByTagName('script')[0];
    if (firstScript && firstScript.parentNode) {
        firstScript.parentNode.insertBefore(script, firstScript);
    } else {
        document.head.appendChild(script);
    }
})();
