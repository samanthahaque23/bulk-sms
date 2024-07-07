<?php
require 'vendor/autoload.php';
require 'folders.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function startsWithUKCode($phoneNumber)
{
    return preg_match('/^44/', $phoneNumber);
}

if (isset($_POST["import"])) {
    $filename = $_FILES["file"]["tmp_name"];
    $fileType = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
    $importSuccess = true;

    if ($_FILES["file"]["size"] > 0) {
        try {
            $conn->begin_transaction();
            if ($fileType === 'csv') {
                $file = fopen($filename, "r");
                $rowNumber = 0;
                $existingNumbers = [];
                while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {
                    $rowNumber++;
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

                    if (in_array($phoneNumber, $existingNumbers)) {
                        continue;
                    }

                    $checkSql = "SELECT * FROM users WHERE phone_number = ?";
                    $stmt = $conn->prepare($checkSql);
                    $stmt->bind_param("s", $phoneNumber);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        continue;
                    }

                    $sql = "INSERT INTO users (first_name, last_name, phone_number) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $firstName, $lastName, $phoneNumber);

                    if (!$stmt->execute()) {
                        $importSuccess = false;
                    } else {
                        $existingNumbers[] = $phoneNumber;
                    }
                }
                fclose($file);
            } else {
                $spreadsheet = IOFactory::load($filename);
                $worksheet = $spreadsheet->getActiveSheet();
                $existingNumbers = [];

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

                    if (in_array($phoneNumber, $existingNumbers)) {
                        continue;
                    }

                    $checkSql = "SELECT * FROM users WHERE phone_number = ?";
                    $stmt = $conn->prepare($checkSql);
                    $stmt->bind_param("s", $phoneNumber);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        continue;
                    }

                    $sql = "INSERT INTO users (first_name, last_name, phone_number) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $firstName, $lastName, $phoneNumber);

                    if (!$stmt->execute()) {
                        $importSuccess = false;
                    } else {
                        $existingNumbers[] = $phoneNumber;
                    }
                }
            }

            if ($importSuccess) {
                $conn->commit();
                echo "<script type=\"text/javascript\">
                        alert(\"File has been successfully imported.\");
                      </script>";
            } else {
                $conn->rollback();
                echo "<script type=\"text/javascript\">
                        alert(\"There were some errors during the import.\");
                      </script>";
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo "<script type=\"text/javascript\">
                    alert(\"Error loading file: " . $conn->error . "\");
                  </script>";
        }
    }
}

if (isset($_POST["create_folder"])) {
    $folder_name = $_POST["folder_name"];
    $sql = "INSERT INTO folders (folder_name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $folder_name);

    if ($stmt->execute()) {
        echo "<script type=\"text/javascript\">
                alert(\"Folder created successfully.\");
              </script>";
    } else {
        echo "<script type=\"text/javascript\">
                alert(\"Failed to create folder.\");
              </script>";
    }
}

if (isset($_POST["add_to_folder"])) {
    if (isset($_POST['phone_numbers']) && !empty($_POST['phone_numbers']) && isset($_POST['folder_id'])) {
        $folder_id = $_POST['folder_id'];
        $phone_numbers = $_POST['phone_numbers'];

        foreach ($phone_numbers as $phone_number) {
            $sql = "INSERT INTO folder_contacts (folder_id, phone_number) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $folder_id, $phone_number);
            $stmt->execute();
        }

        echo "<script type=\"text/javascript\">
                alert(\"Contacts added to folder successfully.\");
              </script>";
    } else {
        echo "<script type=\"text/javascript\">
                alert(\"Please select at least one contact and a folder.\");
              </script>";
    }
}

if (isset($_POST["delete_selected_folder_row"])) {
    if (isset($_POST['phone_numbers']) && !empty($_POST['phone_numbers'])) {
        $phone_numbers = $_POST['phone_numbers'];
        foreach ($phone_numbers as $number) {
            $sql = "DELETE FROM folder_contacts WHERE phone_number = ?";
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
if (isset($_POST["delete_selected"])) {
    if (isset($_POST['phone_numbers']) && !empty($_POST['phone_numbers'])) {
        $phone_numbers = $_POST['phone_numbers'];
        foreach ($phone_numbers as $number) {
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

$sql_folders = "SELECT * FROM folders";
$folders_result = $conn->query($sql_folders);
?>

<!DOCTYPE html>
<html>

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <title>BULK SMS SENDING SYSTEM</title>
    <script type="text/javascript">
        function toggle(source) {
            checkboxes = document.getElementsByName('phone_numbers[]');
            for (var i = 0, n = checkboxes.length; i < n; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
    <style>
        .btn {
            margin: 5px !important;
        }

        a {
            text-decoration: none;
            font-size: 18px;
            font-weight: 600;
            text-transform: capitalize;
            color: #212529;
        }

        li {
            text-align: center;
            width: 100%;
        }

        .list-group {
            width: 500px;
            align-items: center;
        }

        .form-label {
            font-size: 16px;
            color: #212529;
            font-weight: 500;
        }
    </style>
</head>

<body>

    <div>
        <div class="d-flex justify-content-center align-items-center m-4">
            <h1>BULK SMS SENDING SYSTEM</h1>
        </div>
        <form class="form-horizontal" action="" method="post" name="upload_excel" enctype="multipart/form-data">
            <div class="d-flex p-3" style="align-items: center;justify-content:center">
                <div class="d-flex p-3" style="align-items: center;justify-content:center">
                    <h3>Choose Excel or CSV File:</h3>
                    <div><input class="form-control" type="file" name="file" accept=".xls,.xlsx,.csv"></div>
                    <button class='btn btn-info' type="submit" name="import">Import</button>
                    <div></div>
                </div>
            </div>
        </form>
    </div>
    <div class="d-flex justify-content-center align-items-center m-4 flex-column">
        <h2>Folders</h2>
        <form action="" method="post">
            <div>
                <label class="form-label">Create Folder:</label>
                <input class="form-group" type="text" name="folder_name">
                <button class='btn btn-info' type="submit" name="create_folder">Create</button>
            </div>
        </form>
        <ul class="list-group" style="width:50%">
            <?php
            if ($folders_result && $folders_result->num_rows > 0) {
                while ($folder = $folders_result->fetch_assoc()) {
                    echo "<li class='list-group-item'><a href=\"?folder_id=" . $folder['id'] . "\">" . $folder['folder_name'] . "</a></li> ";
                }
                echo ' <a type="button" class="btn btn-info text-center" style="width:300px;text-align-center" data-bs-toggle="modal" data-bs-target="#exampleModal" > Delete Folders </a>';
            } else {
                echo "<li>No folders found</li>";
            }
            ?>
        </ul>
    </div>
    <div class="d-flex justify-content-center align-items-start container">
        <div class="contact-block p-4">
            <?php
            if (isset($_GET['folder_id'])) {
                $folder_id = $_GET['folder_id'];
                $sql_fetch_folder_contacts = "SELECT users.first_name, users.last_name, users.phone_number 
                                      FROM folder_contacts 
                                      JOIN users ON folder_contacts.phone_number = users.phone_number 
                                      WHERE folder_contacts.folder_id = ?";
                $stmt = $conn->prepare($sql_fetch_folder_contacts);
                $stmt->bind_param("i", $folder_id);
                $stmt->execute();
                $folder_contacts_result = $stmt->get_result();
                echo "<button class='btn btn-info'><a href='index.php'/>Return to contacts</button>";
                echo "<h2>Contacts in Folder</h2>";
                echo "<form action=\"\" method=\"post\">";
                echo "<input type=\"hidden\" name=\"folder_id\" value=\"$folder_id\">";
                echo "<table class='table' border=\"1\">
                <thead>
                    <tr>
                        <th><input type=\"checkbox\" onClick=\"toggle(this)\"> Select All</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Phone Number</th>
                    </tr>
                </thead>
                <tbody>";

                if ($folder_contacts_result && $folder_contacts_result->num_rows > 0) {
                    while ($row = $folder_contacts_result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td><input type='checkbox' name='phone_numbers[]' value='" . $row['phone_number'] . "'></td>";
                        echo "<td>" . $row['first_name'] . "</td>";
                        echo "<td>" . $row['last_name'] . "</td>";
                        echo "<td>" . $row['phone_number'] . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No contacts found</td></tr>";
                }

                echo "</tbody>
            </table>
            <button class='btn btn-info' type=\"submit\" name=\"delete_selected_folder_row\">Delete Selected  from folder</button>
            <hr>
            <div>
                <label>Message:</label><br>
                <textarea name=\"message\" rows=\"4\" cols=\"50\"></textarea><br>
                
                <label class='form-label'>Schedule Time:</label>
                <input class='input-group' type=\"datetime-local\" name=\"schedule_time\"><br>
                <label>Or Send Now:</label>
                <input type=\"checkbox\" name=\"send_now\" value=\"1\"><br>
                
                <button class='btn btn-info' type=\"submit\" name=\"send_sms_selected\">Send SMS to Selected Numbers</button>
            </div>
            </form>
            </div>";
            } else {
                echo "</><h2>All Contacts</h2>";
                echo "<form action=\"\" method=\"post\">";
                echo "<table class='table' border=\"1\">
                <thead>
                    <tr>
                        <th><input type=\"checkbox\" onClick=\"toggle(this)\"> Select All</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Phone Number</th>
                    </tr>
                </thead>
                <tbody>";

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

                echo "</tbody>
        </table>
       <div class='d-flex justify-content-between'> 
       <div>
       <button class='btn btn-info' type=\"submit\" name=\"delete_selected\">Delete Selected</button>
       </div>
          <div>
            <select class='form-select' name=\"folder_id\">
                <option value=\"\">Select Folder</option>";

                $sql_folders = "SELECT * FROM folders";
                $folders_result = $conn->query($sql_folders);
                if ($folders_result && $folders_result->num_rows > 0) {
                    while ($folder = $folders_result->fetch_assoc()) {
                        echo "<option value=\"" . $folder['id'] . "\">" . $folder['folder_name'] . "</option>";
                    }
                }

                echo "</select>
        <button class='btn btn-info' type=\"submit\" name=\"add_to_folder\">Add To Selected Folder</button>
          </div>
        </div>
            </div>
    <div class='sms-block p-4'>
        <h3>Send Message:</h3>
        <textarea name=\"message\" rows=\"4\" cols=\"50\"></textarea><br>

        <label class='form-label'>Schedule Time:</label>
        <input type=\"datetime-local\" name=\"schedule_time\"><br>
        <label>Or Send Now:</label>
        <input type=\"checkbox\" name=\"send_now\" value=\"1\"><br>
        
        <button class='btn btn-info' type=\"submit\" name=\"send_sms_selected\">Send SMS to Selected Numbers</button>
    </div>
</form>  ";
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
            }
            ?>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>