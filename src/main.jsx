import blockUI from "block-ui";
/**
 * Download Installed Plugin Script
 *
 * @package Download Installed Plugin
 */
jQuery(document).ready(function ($) {
	/**
	 * Listen for click events on the download button
	 */
	$(".download-installed-plugin").each(function () {
		/**
		 * Handle click event
		 */
		$(this).on("click", function (e) {
			e.preventDefault();
			//get the plugin file from the data-plugin-file attribute
			const pluginFile = $(this).data("plugin-file");
			//ignore if the plugin file is empty
			if (!pluginFile) {
				return;
			}
			//ajax request to download the plugin
			$.ajax({
				url: downloadinstalledplugin.ajax_url,
				type: "POST",
				data: {
					action: "ade_download_installed_plugin",
					nonce: downloadinstalledplugin.nonce,
					plugin_file: pluginFile,
				},
				beforeSend: () => {
					/*
				block the button
				*/
					$(this).block({
						message: null,
						overlayCSS: {
							background: "#fff",
							opacity: 0.5,
						},
						css: {
							border: "0px",
							color: "#fff",
							padding: "15px",
						},
					});
				},
				success: (response) => {
					$(this).unblock();
					//check if the response is a json object
					if (response.success) {
						//download the file in a new tab
						window.open(response.data.download_link, "_blank");
					} else {
						//alert the error
						alert(response.data.message);
					}
				},
				error: (response) => {
					$(this).unblock();
					//alert the error
					alert(response.responseText);
				},
			});
		});
	});
});
