<?php
/**
 * Vercel Speed Insights Integration
 * Include this file in the <head> section of your pages to enable Speed Insights
 * 
 * This integration uses the vanilla JavaScript approach for PHP applications.
 * The script will automatically track Core Web Vitals and performance metrics.
 * 
 * Note: Speed Insights data will only be collected in production environments.
 * For local development, no data is sent to Vercel.
 * 
 * Version: 2.0.0
 * Last updated: <?php echo date('Y-m-d'); ?>
 * 
 * Installation Steps Completed:
 * 1. Installed @vercel/speed-insights package via npm
 * 2. Enabled Speed Insights in Vercel dashboard
 * 3. Added integration script to all application pages
 */
?>
<!-- Vercel Speed Insights -->
<script>
    window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
</script>
<script defer src="/_vercel/speed-insights/script.js" data-sdkn="@vercel/speed-insights" data-sdkv="2.0.0"></script>
