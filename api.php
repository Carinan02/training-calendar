<?php
header("Content-Type: application/json"); //output is in JSON format.
require_once "config.php";
include_once("functions.php");


date_default_timezone_set('Australia/Melbourne');
$action = isset($_GET['action']) ? $_GET['action'] : 'read';

function formatDate($dateValue){

        $dateString = implode(' ', array_slice( explode(' ', $dateValue), 0, 6));

        $timestamp = strtotime($dateString);
        return date('Y-m-d H:i:s', $timestamp);
        
        }
switch($action){
    case 'getAuth':

     
            $user = getuserdetails();
            //echo json_encode(["message" => $user->id]);
           if($user == false){
                    echo json_encode(["success" => false,"message" => "NOT_AUTHENTICATED","relogin" => $relogin]);
                    exit;
            }else{  
                $userid = $user->id;
                    $allowedtoEdit = ['5270','4461','4682','19'];//Nico,Kha, Michelle,G
                    if(in_array($userid,$allowedtoEdit)){
                        echo json_encode(["success" => true,"message" => "EDIT_ACCESS_TRUE"]);
                    }else{
                        echo json_encode(["success" => true,"message" => "EDIT_ACCESS_FALSE"]);
                    }
                 
                    exit;
            }
            

            
        break;

    case 'getTrainers':
        $getTrainersQ = "SELECt vd_firstname,vd_employeeid FROM aa_vocusdirectory WHERE vd_team = :LD AND vd_status = :STATUS ";
        $stmt = $dbh->prepare($getTrainersQ);
        $stmt->execute(array(':LD' => 'LD',':STATUS' => 'ACTIVE'));
        $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($trainers);

        break;
    case 'read':

        $fstatusClean = "";
        $fstatus = isset($_COOKIE['controlStatus']) ? json_decode($_COOKIE['controlStatus']) : "";
        
        if (is_array($fstatus) && !empty($fstatus)) {
                foreach ($fstatus as $fstatusItem) {
                    $fstatusClean .= "'" . htmlentities($fstatusItem) . "',";
                }
                $Qstatus = " AND tp_status IN (" . trim($fstatusClean, ',') . ") ";
            } else {
                $Qstatus = "";
            }

        $ffacilitatorClean = "";
        $ffacilitator = isset($_COOKIE['controlFacilitator']) ? json_decode($_COOKIE['controlFacilitator']) : "";
        
        if (is_array($ffacilitator) && !empty($ffacilitator)) {
                foreach ($ffacilitator as $ffacilitatorItem) {
                    $ffacilitatorClean .= "'" . htmlentities($ffacilitatorItem) . "',";
                }
                $Qfacilitator = " AND vd_employeeid IN (" . trim($ffacilitatorClean, ',') . ") ";
            } else {
                $Qfacilitator = "";
            }
        
        $tpDateRangeStart = isset($_COOKIE['tpDateRangeStart']) ? $_COOKIE['tpDateRangeStart'] : "";
        $tpDateRangeEnd = isset($_COOKIE['tpDateRangeEnd']) ? $_COOKIE['tpDateRangeEnd'] : "";
        
        $QdateRange = "";
        if($tpDateRangeStart != ""){
           $QdateRange =" AND tp_startDateFormatted <= '".$tpDateRangeEnd."' AND tp_endDateFormatted >= '".$tpDateRangeStart."'";
        }

        $sorter = isset($_COOKIE['tp_sorter']) ? $_COOKIE['tp_sorter'] : "";
        $Qsorter = "";
        if($sorter != ""){
        $sortType = isset($_COOKIE['tp_sortType']) ? $_COOKIE['tp_sortType'] : "";

           $Qsorter =" ORDER BY  ".$sorter." ".$sortType;
        }



        $sql = "SELECT tp_trainingClass.*, aa_vocusdirectory.vd_firstname FROM tp_trainingClass LEFT JOIN aa_vocusdirectory ON tp_trainingClass.tp_trainer = aa_vocusdirectory.vd_employeeid where tp_isArchived = '0' AND tp_status != 'Deleted'  ".$Qstatus.$QdateRange.$Qfacilitator.$Qsorter;

       //echo json_encode(array("success" => true, "message" => $sql));
      // exit;

        $stmt = $dbh->query($sql);
        $tasks = [];
        while ($row = $stmt->fetch()) {
            $status = "";
            $formatted_start = date("d M Y", strtotime($row["tp_startDateFormatted"]));
            $formatted_end = date("d M Y", strtotime($row["tp_endDateFormatted"]));
            if($row["tp_status"]=='Completed'){
                $status = "Completed";
            }else if($row["tp_status"]=='In Progress'){
                 $status = "In_Progress";
            }else if($row["tp_status"]=='Not Started'){
                 $status = "Not_Started";
            }else if($row["tp_status"]=='Proposed'){
                 $status = "Proposed";
            }

            $trainer = $row["vd_firstname"] == null ? '' : $row["vd_firstname"];
            $tasks[] = [
                "id" => $row["tp_id"],
                "name" => $row["tp_className"],
                "brand" => $row["tp_brand"],
                "type" => $row["tp_type"],
                "start" => $row["tp_startDate"],
                "end" => $row["tp_endDate"],
                "trainer" =>  $trainer,   // custom
                "status" => $row["tp_status"]   ,           // custom
                "custom_class" => $status,
                "formatted_start"        => $formatted_start,
                "formatted_end"          => $formatted_end,
            ];
        }
        echo json_encode($tasks);
        break;
    case 'newClass':
        $className = isset($_POST['className']) ? $_POST['className'] : '';
        $classStart = isset($_POST['classStart']) ? $_POST['classStart'] : '';
        $classEnd = isset($_POST['classEnd']) ? $_POST['classEnd'] : '';
        $classStatus = isset($_POST['classStatus']) ? $_POST['classStatus'] : '';
        $classFacilitator = isset($_POST['classFacilitator']) ? $_POST['classFacilitator'] : '';
        $classNotes = isset($_POST['classNotes']) ? $_POST['classNotes'] : '';
        $classBrand = isset($_POST['classBrand']) ? $_POST['classBrand'] : '';
        $classType = isset($_POST['classType']) ? $_POST['classType'] : '';
        

        $start_formatted = formatDate($classStart);
        $end_formatted = formatDate($classEnd);

        if ($className && $classStart && $classEnd ) {

            $sql = "INSERT INTO tp_trainingClass (tp_className,tp_status) VALUES (:className, :classStatus)";
			$stmt = $dbh->prepare($sql);
			$stmt->execute(array(':className' => $className,
									':classStatus' => $classStatus));

			$lastID = $dbh->lastInsertId();
			$classCode = "CLASS-".$lastID;


            $stmt = $dbh->prepare("UPDATE tp_trainingClass SET tp_code = :tp_code, tp_brand = :tp_brand, tp_type = :tp_type, tp_trainer = :tp_trainer, tp_startDate = :classStart, tp_endDate = :classEnd, tp_startDateFormatted = :start_formatted, tp_endDateFormatted = :end_formatted, tp_notes = :classNotes WHERE tp_id = :lastID");
            $ok = $stmt->execute(array(':classStart' => $classStart,
                                        ':tp_brand' => $classBrand,
                                        ':tp_type' => $classType,
                                        ':classEnd'=> $classEnd, 
                                        ':start_formatted'=> $start_formatted, 
                                        ':end_formatted' => $end_formatted,
                                        ':tp_trainer' => $classFacilitator, 
                                        ':tp_code' => $classCode,
                                        ':classNotes' => $classNotes,
                                        ':lastID' => $lastID));
            
        echo json_encode(array("success" => true, "message" => "Added Successfully"));
        } else {
        echo json_encode(array("success" => false, "message" => "Missing parameters"));
        }


        break;
    case 'update':
        

        $id    = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $start = isset($_POST['start']) ? $_POST['start'] : null;
        $end   = isset($_POST['end']) ? $_POST['end'] : null;

        $start_formatted = formatDate($start);
        $end_formatted = formatDate($end);

       
        
        if ($id && $start && $end) {
        $stmt = $dbh->prepare("UPDATE tp_trainingClass SET tp_startDate = ?, tp_endDate = ?, tp_startDateFormatted = ?, tp_endDateFormatted = ? WHERE tp_id = ?");
        $ok = $stmt->execute(array($start, $end, $start_formatted,  $end_formatted, $id));

        if ($ok) {//GET SINGLE UPDATED VALUE THEN UPDATE TASKS IN THE INDEX.PHP
             $stmt = $dbh->query("SELECT * FROM tp_trainingClass where tp_id = ? ");
             $stmt->execute(array($id));
             $updatedRow = $stmt->fetch();
                $status = "";
                if($updatedRow["tp_status"]=='Completed'){
                    $status = "Completed";
                }else if($updatedRow["tp_status"]=='In Progress'){
                    $status = "In_Progress";
                }else if($updatedRow["tp_status"]=='Not Started'){
                    $status = "Not_Started";
                }else if($updatedRow["tp_status"]=='Proposed'){
                    $status = "Proposed";
                }
$formatted_start = date("d M Y", strtotime($updatedRow["tp_startDateFormatted"]));
    $formatted_end = date("d M Y", strtotime($updatedRow["tp_endDateFormatted"]));
              $tasks = [
                "id"           => $updatedRow["tp_id"],
        "name"         => $updatedRow["tp_className"],
        "start"        => $updatedRow["tp_startDateFormatted"],
        "end"          => $updatedRow["tp_endDateFormatted"],
        "formatted_start"        => $formatted_start,
        "formatted_end"          => $formatted_end,
        "trainerEID"      => $updatedRow["tp_trainer"],
        "status"       => $updatedRow["tp_status"],
        "custom_class" => $status,
        "notes" => $updatedRow["tp_notes"]
            ];
            echo json_encode($tasks);
     
        } else {
            echo json_encode(array("success" => false, "message" => "Update failed"));
        }
    } else {
        echo json_encode(array("success" => false, "message" => "Missing parameters"));
    }
        break;
    case 'getSolo': 
try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // Prepare the statement to prevent SQL injection.
    $stmt = $dbh->prepare("SELECT tp_trainingClass.*, aa_vocusdirectory.vd_firstname FROM tp_trainingClass LEFT JOIN aa_vocusdirectory ON tp_trainingClass.tp_trainer = aa_vocusdirectory.vd_employeeid WHERE tp_id = ?");
    
    // Execute the statement with the user-provided ID.
    $stmt->execute(array($id));
    
    $updatedRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$updatedRow) {
        echo json_encode(array(
            "success" => false,
            "message" => "Record not found."
        ));
        exit;
    }
    
    $status = "";
    if ($updatedRow["tp_status"] == 'Completed') {
        $status = "Completed";
    } else if ($updatedRow["tp_status"] == 'In Progress') {
        $status = "In_Progress";
    } else if ($updatedRow["tp_status"] == 'Not Started') {
        $status = "Not_Started";
    } else if ($updatedRow["tp_status"] == 'Proposed') {
        $status = "Proposed";
    }
    $trainer = $updatedRow["vd_firstname"] == null ? '' : $updatedRow["vd_firstname"];
    $formatted_start = date("d M Y", strtotime($updatedRow["tp_startDateFormatted"]));
    $formatted_end = date("d M Y", strtotime($updatedRow["tp_endDateFormatted"]));
    $tasks = array(
        "id"           => $updatedRow["tp_id"],
        "name"         => $updatedRow["tp_className"],
        "type"         => $updatedRow["tp_type"],
        "brand"         => $updatedRow["tp_brand"],
        "start"        => $updatedRow["tp_startDateFormatted"],
        "end"          => $updatedRow["tp_endDateFormatted"],
        "formatted_start"        => $formatted_start,
        "formatted_end"          => $formatted_end,
        "trainer"      => $trainer,
        "trainerEID"      => $updatedRow["tp_trainer"],
        "status"       => $updatedRow["tp_status"],
        "custom_class" => $status,
        "notes" => $updatedRow["tp_notes"]

    );
    
    echo json_encode($tasks);

} catch (PDOException $e) {
    // Catch the specific database exception.
    echo json_encode(array(
        "success" => false,
        "message" => "A database error occurred."
    ));
} catch (Exception $e) {
    // A generic catch-all for other unexpected exceptions.
    echo json_encode(array(
        "success" => false,
        "message" => "An unexpected error occurred."
    ));
}


       
        break;
    case 'updateSolo':

        $className = isset($_POST['edit-ClassName']) ? $_POST['edit-ClassName'] : '';
        $classStart = isset($_POST['edit-start-date']) ? $_POST['edit-start-date'] : '';
        $classEnd = isset($_POST['edit-end-date']) ? $_POST['edit-end-date'] : '';
        $classStatus = isset($_POST['edit-status']) ? $_POST['edit-status'] : '';
        $classFacilitator = isset($_POST['classFacilitator']) ? $_POST['classFacilitator'] : '';
        $classNotes = isset($_POST['edit-notes']) ? $_POST['edit-notes'] : '';
        $classBrand = isset($_POST['edit-brand']) ? $_POST['edit-brand'] : '';
        $classType = isset($_POST['edit-type']) ? $_POST['edit-type'] : '';

        $tp_id = isset($_POST['edit-ClassId']) ? intval($_POST['edit-ClassId']) : 0;

        $start_formatted = formatDate($classStart);
        $end_formatted = formatDate($classEnd);

        if ($className && $classStart && $classEnd ) {

            $stmt = $dbh->prepare("UPDATE tp_trainingClass SET tp_status = :tp_status, tp_brand = :tp_brand, tp_type = :tp_type, tp_className =:tp_className, tp_trainer = :tp_trainer, tp_startDate = :classStart, tp_endDate = :classEnd, tp_startDateFormatted = :start_formatted, tp_endDateFormatted = :end_formatted, tp_notes = :classNotes WHERE tp_id = :tp_id");
            $ok = $stmt->execute(array(':classStart' => $classStart,
                                        ':classEnd'=> $classEnd, 
                                        ':start_formatted'=> $start_formatted, 
                                        ':end_formatted' => $end_formatted,
                                        ':tp_trainer' => $classFacilitator, 
                                        ':tp_status' => $classStatus, 
                                        ':tp_className' => $className, 
                                        ':tp_brand' => $classBrand, 
                                        ':tp_type' => $classType,
                                        
                                        ':classNotes' => $classNotes,
                                        ':tp_id' => $tp_id));
            
        echo json_encode(array("success" => true, "message" => "Updated Successfully"));
        } else {
        echo json_encode(array("success" => false, "message" => "Missing parameters"));
        }

        break;
    case 'deleteSolo':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        try {
        $stmt = $dbh->prepare("UPDATE tp_trainingClass SET tp_status = :tp_status WHERE tp_id = :tp_id");
            $ok = $stmt->execute(array(':tp_status' => 'Deleted',
                                        ':tp_id' => $id));


        echo json_encode(array("success" => true, "message" => "Deleted Successfully"));
         } catch (Exception $e) {
        echo json_encode([
                "success" => false,
                "message" => $e->getMessage()
            ]);
        }
        break;

    default :
        echo json_encode(array("success" => false, "message" => "Invalid action"));
    break;
}
?>