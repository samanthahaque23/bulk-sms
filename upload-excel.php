<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function startsWithUKCode($phoneNumber) {
    return preg_match('/^\+44/', $phoneNumber);
}

if (isset($_POST["import"])) {
    $filename = $_FILES["file"]["tmp_name"];
    $fileType = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
    $importSuccess = true;

    if ($_FILES["file"]["size"] > 0) {
        try {
            if ($fileType === 'csv') {
                $file = fopen($filename, "r");
                $rowNumber = 0;
                while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {
                    $rowNumber++;
                    // Skip the header row
                    if ($rowNumber == 1) continue;

                    if (count($data) < 3) {
                        continue;
                    }

                    $firstName = $data[0];
                    $lastName = $data[1];
                    $phoneNumber = $data[2];

                    if (!startsWithUKCode($phoneNumber)) {
                        continue;
                    }

                    $sql = "INSERT INTO users (first_name, last_name, phone_number) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $firstName, $lastName, $phoneNumber);

                    if (!$stmt->execute()) {
                        $importSuccess = false;
                    }
                }
                fclose($file);
            } else {
                $spreadsheet = IOFactory::load($filename);
                $worksheet = $spreadsheet->getActiveSheet();

                foreach ($worksheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(FALSE);

                    $data = [];
                    foreach ($cellIterator as $cell) {
                        $data[] = $cell->getValue();
                    }

                    if (count($data) < 3) {
                        continue;
                    }

                    $firstName = $data[0];
                    $lastName = $data[1];
                    $phoneNumber = $data[2];

                    if (!startsWithUKCode($phoneNumber)) {
                        continue;
                    }

                    $sql = "INSERT INTO users (first_name, last_name, phone_number) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $firstName, $lastName, $phoneNumber);

                    if (!$stmt->execute()) {
                        $importSuccess = false;
                    }
                }
            }

            if ($importSuccess) {
                echo "<script type=\"text/javascript\">
                        alert(\"File has been successfully imported.\");
                      </script>";
            } else {
                echo "<script type=\"text/javascript\">
                        alert(\"There were some errors during the import.\");
                      </script>";
            }

        } catch (Exception $e) {
            echo "<script type=\"text/javascript\">
                    alert(\"Error loading file: " . $e->getMessage() . "\");
                  </script>";
        }
    }
}

if (isset($_POST["delete_selected"])) {
    if (isset($_POST['phone_numbers']) && !empty($_POST['phone_numbers'])) {
        $selected_numbers = $_POST['phone_numbers'];
        foreach ($selected_numbers as $number) {
            $sql = "DELETE FROM users WHERE phone_number = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $number);
            $stmt->execute();
        }
        echo "<script type=\"text/javascript\">
                alert(\"Selected records have been deleted.\");
              </script>";
    } else {
        echo "<script type=\"text/javascript\">
                alert(\"Please select at least one record to delete.\");
              </script>";
    }
}

$sql_fetch = "SELECT first_name, last_name, phone_number FROM users";
$result = $conn->query($sql_fetch);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Excel or CSV File into MySQL and Send Bulk SMS using PHP</title>
    <script type="text/javascript">
        function toggle(source) {
            checkboxes = document.getElementsByName('phone_numbers[]');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</head>
<body>
    <form class="form-horizontal" action="" method="post" name="upload_excel" enctype="multipart/form-data">
        <div>
            <label>Choose Excel or CSV File:</label>
            <input type="file" name="file" accept=".xls,.xlsx,.csv">
            <button type="submit" name="import">Import</button>
        </div>
    </form>

    <hr>

    <h2>Imported Data</h2>
    <form action="" method="post">
        <table border="1">
            <thead>
                <tr>
                    <th><input type="checkbox" onClick="toggle(this)"> Select All</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Phone Number</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td><input type='checkbox' name='phone_numbers[]' value='" . $row['phone_number'] . "'></td>";
                        echo "<td>" . $row['first_name'] . "</td>";
                        echo "<td>" . $row['last_name'] . "</td>";
                        echo "<td>" . $row['phone_number'] . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No data found</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <hr>

        <div>
            <button type="submit" name="delete_selected">Delete Selected</button>
        </div>

        <hr>

        <div>
            <label>Message:</label><br>
            <textarea name="message" rows="4" cols="50"></textarea><br>
            
            <label>Schedule Time:</label>
            <input type="datetime-local" name="schedule_time"><br>
            <label>Or Send Now:</label>
            <input type="checkbox" name="send_now" value="1"><br>
            
            <button type="submit" name="send_sms_selected">Send SMS to Selected Numbers</button>
        </div>
    </form>

    <?php
    if (isset($_POST["send_sms_selected"])) {
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $apiKey = 'NTM0MzM1NDc3NzRjNDE3NDMwNmU2MjcwNzk0MzMzNDI=';  
        $sender = 'samantha';  
        $message = $_POST["message"];
        $schedule_time = $_POST["schedule_time"];
        $send_now = isset($_POST["send_now"]) ? $_POST["send_now"] : 0;

        if (isset($_POST['phone_numbers']) && !empty($_POST['phone_numbers'])) {
            $selected_numbers = $_POST['phone_numbers'];
            $numbers = implode(',', $selected_numbers);

            if ($send_now) {
                $data = array(
                    'apikey' => $apiKey,
                    'numbers' => $numbers,
                    'sender' => $sender,
                    'message' => $message
                );

                $ch = curl_init('https://api.textlocal.in/send/');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_close($ch);

                $status = (strpos($response, '"status":"success"') !== false) ? 'Success' : 'Failure';

                $insert_sql = "INSERT INTO sms_info (phone_number, message, scheduled_time, status) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("ssss", $number, $message, $schedule_time, $status);

                foreach ($selected_numbers as $number) {
                    $stmt->execute();
                }

                echo "<script type=\"text/javascript\">
                        alert(\"Bulk SMS Sent Successfully.\");
                      </script>";
            } else {
                $status = 'Scheduled';
                $insert_sql = "INSERT INTO sms_info (phone_number, message, scheduled_time, status) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("ssss", $number, $message, $schedule_time, $status);

                foreach ($selected_numbers as $number) {
                    $stmt->execute();
                }

                echo "<script type=\"text/javascript\">
                        alert(\"Messages Scheduled Successfully.\");
                      </script>";
            }
        } else {
            echo "<script type=\"text/javascript\">
                    alert(\"Please select at least one phone number.\");
                  </script>";
        }

        $conn->close();
    }
    ?>
</body>
</html>
