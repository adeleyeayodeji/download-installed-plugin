import React from "react";
import { createRoot, StrictMode } from "@wordpress/element";

import "./assets/sass/style.scss";

//include the functions
import "./functions/functions.jsx";
import BiggiDroidHome from "./components/pages/admin/home/BiggiDroidHome";

//check if id exists #biggidroid-backup-restore
const biggiDroidBackupRestore = document.getElementById(
	"biggidroid-backup-restore",
);
if (biggiDroidBackupRestore) {
	//init react app
	const root = createRoot(biggiDroidBackupRestore);
	//render the app
	root.render(<BiggiDroidHome />);
}
