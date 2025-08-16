document.addEventListener("DOMContentLoaded", function () {
    const monthSelect = document.getElementById("month-select");
    const yearSelect = document.getElementById("year-select");
    
    // Populate month and year dropdowns
    populateMonthYearSelectors();

    // Event listener for user click
    window.loadAttendance = function (userId) {
        const month = monthSelect.value;
        const year = yearSelect.value;
        
        fetchAttendance(userId, month, year);
    };

    // Fetch attendance via AJAX
    function fetchAttendance(userId, month, year) {
        fetch("get_attendance.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ user_id: userId, month: month, year: year })
        })
        .then(response => response.json())
        .then(data => displayAttendance(data))
        .catch(error => console.error("Error fetching attendance:", error));
    }

    // Display attendance on the calendar
    function displayAttendance(attendance) {
        const calendarTitle = document.getElementById("calendar-title");
        const calendarTable = document.getElementById("calendar-table");

        // Clear the calendar
        calendarTable.innerHTML = "";

        // Update calendar title
        calendarTitle.textContent = `${monthSelect.options[monthSelect.selectedIndex].text} ${yearSelect.value}`;

        // Create a simple table layout for attendance
        if (attendance.length > 0) {
            attendance.forEach(entry => {
                const row = document.createElement("tr");
                row.innerHTML = `<td>${entry.date}</td><td>${entry.checkin_time}</td><td>${entry.checkout_time}</td>`;
                calendarTable.appendChild(row);
            });
        } else {
            calendarTable.innerHTML = "<tr><td colspan='3'>No attendance records found</td></tr>";
        }
    }

    function populateMonthYearSelectors() {
        for (let i = 1; i <= 12; i++) {
            const option = document.createElement("option");
            option.value = i;
            option.textContent = new Date(0, i - 1).toLocaleString("default", { month: "long" });
            monthSelect.appendChild(option);
        }

        const currentYear = new Date().getFullYear();
        for (let i = currentYear - 5; i <= currentYear + 1; i++) {
            const option = document.createElement("option");
            option.value = i;
            option.textContent = i;
            yearSelect.appendChild(option);
        }

        // Set default to current month and year
        monthSelect.value = new Date().getMonth() + 1;
        yearSelect.value = currentYear;
    }
});
