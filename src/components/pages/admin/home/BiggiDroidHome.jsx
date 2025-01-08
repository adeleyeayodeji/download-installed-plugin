import React, { useEffect, useState } from "react";

export default function BiggiDroidHome() {
	const [isBackupInProgress, setIsBackupInProgress] = useState(false);
	const [backupProgress, setBackupProgress] = useState(0);
	const [backupError, setBackupError] = useState(null);
	const [backupMessage, setBackupMessage] = useState({
		message: "",
		type: "",
	});
	/**
	 * On page load, hide all notices
	 */
	useEffect(() => {
		const notices = document.querySelectorAll(".notice");
		notices.forEach((notice) => {
			notice.style.display = "none";
		});
	}, []);

	/**
	 * Handle file upload
	 */
	const handleFileChange = (e) => {
		console.log(e.target.files[0]);
	};

	const handleBackupNow = (e) => {
		e.preventDefault();
		//ajax request to backup the site
		jQuery.ajax({
			url: downloadinstalledextension.ajax_url,
			type: "POST",
			data: {
				action: "biggidroid_backup_wordpress_site",
				nonce: downloadinstalledextension.nonce,
			},
			beforeSend: () => {
				setIsBackupInProgress(true);
				setBackupMessage({
					message: "Backup in progress...",
					type: "info",
				});
			},
			success: (response) => {
				console.log(response);
				setIsBackupInProgress(false);
				//check if response is success
				if (response.success) {
					setBackupMessage({
						message: response.data.message,
						type: "success",
					});
				} else {
					setBackupMessage({
						message: response.data.message,
						type: "error",
					});
				}
			},
			error: (error) => {
				setIsBackupInProgress(false);
				setBackupMessage({
					message: "Backup failed, please try again.",
					type: "error",
				});
			},
		});
	};

	return (
		<div className="biggidroid-backup-container">
			<div className="biggidroid-backup-container-header">
				<h1>BiggiDroid Backup & Restore</h1>
				<p>
					Backup and restore your WordPress site with ease using
					BiggiDroid.
				</p>
				{backupMessage.message != "" && (
					<div className="biggidroid-backup-container-header-message">
						<p
							className={`biggidroid-backup-container-header-message-${backupMessage.type}`}
						>
							{backupMessage.message}
						</p>

						{isBackupInProgress && <p>20% completed</p>}
					</div>
				)}
			</div>
			<div className="biggidroid-backup-container-wrapper">
				<div className="biggidroid-backup-container-wrapper-left">
					<div className="biggidroid-backup-container-wrapper-left-header">
						<h2>Backup WordPress Site</h2>
					</div>
					<div className="biggidroid-backup-container-wrapper-left-content">
						<p>Backup your WordPress site to a local file.</p>
					</div>
					<div className="biggidroid-backup-container-wrapper-left-button">
						<button onClick={handleBackupNow}>Backup Now</button>
					</div>
				</div>
				<div className="biggidroid-backup-container-wrapper-right">
					<div className="biggidroid-backup-container-wrapper-right-header">
						<h2>Restore</h2>
					</div>
					<div className="biggidroid-backup-container-wrapper-right-content">
						<p>Restore your WordPress site from a local file.</p>
					</div>
					<div className="biggidroid-backup-container-wrapper-right-fileupload">
						<input
							type="file"
							accept=".zip"
							onChange={handleFileChange}
							placeholder="Upload Backup File"
						/>
					</div>
					<div className="biggidroid-backup-container-wrapper-right-button">
						<button>Restore Now</button>
					</div>
				</div>
			</div>
		</div>
	);
}
