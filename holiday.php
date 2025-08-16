<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday List 2024</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .container2 {
            width: 80%;
            margin: 0 auto;
        }
        .header, .left {
            margin-bottom: 20px;
        }
        .holiday-tail{
            display: flex;
            justify-content: space-around;
            background-color:black;
            font-size: 25px;
        }
    </style>
</head>
<body>
    <div class="container2">
        <div class="header">
            <!-- Include header -->
            <?php include "header.php"; ?>
        </div>

        <div class="body-container">
            <div class="left">
                <!-- Left sidebar (includes leaves_request.php) -->
                <?php include 'leaves_request.php'; ?>
            </div>

            <div class="right">
                <!-- Main content: Holiday List Table -->
                <h2>Holiday List 2024</h2>
                <table id="holidayTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Holidays</th>
                            <th>Type of Holiday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>01 Jan</td>
                            <td>Monday</td>
                            <td>New Year's Day</td>
                            <td>CH</td>
                        </tr>
                        <tr>
                            <td>13 Jan</td>
                            <td>Saturday</td>
                            <td>Lohri</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>14 Jan</td>
                            <td>Sunday</td>
                            <td>Makar Sakranti</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>15 Jan</td>
                            <td>Monday</td>
                            <td>Pongal</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>26 Jan</td>
                            <td>Friday</td>
                            <td>Republic Day</td>
                            <td>GH</td>
                        </tr>
                        <tr>
                            <td>14 Feb</td>
                            <td>Wednesday</td>
                            <td>Vasant Panchami</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>08 Mar</td>
                            <td>Friday</td>
                            <td>Maha Shivratri</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>25 Mar</td>
                            <td>Monday</td>
                            <td>Holi</td>
                            <td>CH</td>
                        </tr>
                        <tr>
                            <td>29 Mar</td>
                            <td>Friday</td>
                            <td>Good Friday</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>09 Apr</td>
                            <td>Tuesday</td>
                            <td>Gudi Padwa</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>11 Apr</td>
                            <td>Thursday</td>
                            <td>Ramzan Id/Eid-ul-Fitar</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>17 Apr</td>
                            <td>Wednesday</td>
                            <td>Rama Navmi</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>21 Apr</td>
                            <td>Sunday</td>
                            <td>Mahavir Jayanti</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>23 May</td>
                            <td>Thursday</td>
                            <td>Buddha Purnima/Vesak</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>17 Jun</td>
                            <td>Monday</td>
                            <td>Bakrid/Eid-ul-Adha</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>17 Jul</td>
                            <td>Wednesday</td>
                            <td>Muharram/Ashura (Tentative Date)</td>
                            <td>GH</td>
                        </tr>
                        <tr>
                            <td>15 Aug</td>
                            <td>Thursday</td>
                            <td>Independence Day</td>
                            <td>CH</td>
                        </tr>
                        <tr>
                            <td>19 Aug</td>
                            <td>Monday</td>
                            <td>Raksha Bandhan</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>26 Aug</td>
                            <td>Monday</td>
                            <td>Janmashtami</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>07 Sep</td>
                            <td>Saturday</td>
                            <td>Ganesh Chaturti</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>16 Sep</td>
                            <td>Monday</td>
                            <td>Milad un-Nabi/Id-e-Milad (Tentative Date)</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>02 Oct</td>
                            <td>Wednesday</td>
                            <td>Mahatma Gandhi Jayanti</td>
                            <td>GH</td>
                        </tr>
                        <tr>
                            <td>12 Oct</td>
                            <td>Saturday</td>
                            <td>Dussehra</td>
                            <td>CH</td>
                        </tr>
                        <tr>
                            <td>31 Oct</td>
                            <td>Thursday</td>
                            <td>Diwali</td>
                            <td>CH</td>
                        </tr>
                        <tr>
                            <td>01 Nov</td>
                            <td>Friday</td>
                            <td>Govardhan Puja</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>03 Nov</td>
                            <td>Sunday</td>
                            <td>Bhai Duj</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>07 Nov</td>
                            <td>Thursday</td>
                            <td>Chhat Puja</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>15 Nov</td>
                            <td>Friday</td>
                            <td>Guru Nanak Jayanti</td>
                            <td>RH</td>
                        </tr>
                        <tr>
                            <td>25 Dec</td>
                            <td>Wednesday</td>
                            <td>Christmas</td>
                            <td>RH</td>
                        </tr>
                    </tbody>
                </table>

                <div class="holiday-tail">
                    <div style="color:#ffcccc;">CH - Company Holiday</div>
                    <div style="color:#ccffd9;">RH - Restricted Holiday</div>
                    <div style="color:#cce5ff;">GH - Gazatted Holiday</div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Get all rows in the table body
        const rows = document.querySelectorAll('#holidayTable tbody tr');

        // Loop through each row and set background color based on the "Type of Holiday" column
        rows.forEach(row => {
            const typeOfHoliday = row.cells[3].textContent.trim();  // Get text in the last cell
            if (typeOfHoliday === 'CH') {
                row.style.backgroundColor = '#ffcccc';  // Light Red for Company Holiday
            } else if (typeOfHoliday === 'GH') {
                row.style.backgroundColor = '#cce5ff';  // Light Blue for Gazetted Holiday
            } else if (typeOfHoliday === 'RH') {
                row.style.backgroundColor = '#ccffd9';  // Light Green for Restricted Holiday
            }
        });
    </script>

</body>
</html>
