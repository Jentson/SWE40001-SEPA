<?php
session_start();
require_once "../db.php";
require_once "../sanitizeInput.php";
require_once "../Student/StudentInfo.php";

ini_set('log_errors', 1);
ini_set('error_log', '../error.log');

if (!isset($_SESSION['stud_id'])) {
    echo '<script>alert("You need to login first!");</script>';
    echo '<script>window.location.href = "LoginForStudent.html";</script>';
    exit();
}

$student_id = $_SESSION['stud_id'];
$studentInfo = getStudentInfo($conn, $student_id);
$student_name = $studentInfo['stud_name'];
$leave_ref = $student_id;

ob_start();

if (isset($_POST['Submit'])) {
    // File upload
    $pdfFile = $_FILES['files'];
    $fileName = $_FILES['files']['name'];
    $fileTmpName = $_FILES['files']['tmp_name'];
    $fileType = mime_content_type($fileTmpName);

    if ($fileType === 'application/pdf') {
        $destination = '../file/' . uniqid() . '_' . $fileName; // Unique filename
        if (!move_uploaded_file($fileTmpName, $destination)) {
            error_log("Error moving file to destination.");
            exit();
        }
    } else {
        echo '<script>alert("Only PDF files are allowed. Please refile")</script>';
        echo '<script>window.location.href = "LeaveApplication.php";</script>';
        exit();
    }

    // Retrieve form data
    $startLeave = $_POST['startDate'];
    $endLeave = $_POST['endDate'];
    $reason = sanitizeInput($_POST['inputDescription']);
    $addedSubjects = isset($_POST['addedSubjects']) ? json_decode($_POST['addedSubjects'], true) : [];

    $assignment_successful = true;

    if (!empty($addedSubjects)) {
        // Insert the data into the database for each added subject
        foreach ($addedSubjects as $subject) {
            $lecturer_status = "Pending";
            $hop_status = "Pending";
            $query = "INSERT INTO leave_application (leave_ref, stud_id, stud_name, subj_code, startDate, endDate, documents, reason, lecturer_approval_status, hop_approval) 
            VALUES ('$leave_ref','$student_id', '$student_name', '" . mysqli_real_escape_string($conn, $subject['code']) . "', '$startLeave', '$endLeave', '$destination', '" . mysqli_real_escape_string($conn, $reason) . "', 
            '$lecturer_status', '$hop_status')";
            $result = mysqli_query($conn, $query);

            if ($result) {
                $leave_id = mysqli_insert_id($conn);
                $lecturer_id_query = "SELECT staff_id FROM subject WHERE subj_code = '" . mysqli_real_escape_string($conn, $subject['code']) . "'";
                $lecturer_id_result = mysqli_query($conn, $lecturer_id_query);
            if ($lecturer_id_result) {
                    $lecturer_id_row = mysqli_fetch_assoc($lecturer_id_result);
                    $lecturer_id = $lecturer_id_row['staff_id'];
                    // Insert record into lecturer_approval table
                    $request_query = "INSERT INTO lecturer_approval (leave_id, lecturer_id, lect_status) 
                    VALUES ('$leave_id', '$lecturer_id', '0')";
                    $approval_result = mysqli_query($conn, $request_query);

                    //Insert into HOP_table
                    $hop_application = "INSERT INTO hop_approval (leave_id, hop_approval, process)
                    VALUES ('$leave_id', '123460', '0')";
                    $hop_result = mysqli_query($conn, $hop_application);

                    if (!$hop_result) {
                        $assignment_successful = false;
                        error_log("Error inserting record into hop_approval table" . mysqli_error($conn));
                    }
                    
                    if (!$approval_result) {
                        $assignment_successful = false;
                        error_log("Error inserting record into lecturer_approval table for subject " . $subject['code'] . ": " . mysqli_error($conn));
                    }
                } else {
                    $assignment_successful = false;
                    error_log("Error retrieving lecturer ID for subject " . $subject['code'] . ": " . mysqli_error($conn));
                }
            } else {
                $assignment_successful = false;
                error_log("Error inserting leave application for subject " . $subject['code'] . ": " . mysqli_error($conn));
            }

        }
    } else {
        error_log("No subjects were added.");
    }

    if ($assignment_successful) {
        ob_end_clean();
        echo '<script>alert("Leave application submitted")</script>';
        echo '<script>window.location.href = "StudentMain.php";</script>';
        exit();
    } else {
        echo "Error submitting leave applications ." . mysqli_error($conn);
    }
}

mysqli_close($conn);
?>
