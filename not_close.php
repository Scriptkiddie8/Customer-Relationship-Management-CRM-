<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Window</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .disabled {
            pointer-events: none; /* Disable interaction */
            opacity: 0.5; /* Visual indication of being disabled */
        }
    </style>
</head>
<body>
    <button id="openPopup">Open Form Popup</button>

    <script>
        document.getElementById("openPopup").addEventListener("click", function() {
            const popup = window.open("notclose.html", "PopupWindow", "width=600,height=400");

            // Disable main window interactions
            document.body.classList.add("disabled");

            // Check if popup is blocked
            if (popup) {
                popup.focus();
            } else {
                alert("Popup blocked! Please allow popups for this site.");
            }

            // Handle popup closing
            const checkPopupClosed = setInterval(function() {
                if (popup.closed) {
                    clearInterval(checkPopupClosed);
                    document.body.classList.remove("disabled"); // Re-enable main window
                }
            }, 500);
        });
    </script>
</body>
</html>
