<?php
header('Content-Type: application/json');
$dir          = "./librarydata"; //path

$list = array(); //main array

if(is_dir($dir)){
    if($dh = opendir($dir)){
        while(($file = readdir($dh)) != false){

            if($file == "." or $file == ".."){
                //...
            } else { //create object with two fields
                $front = explode(".", $file)[0];
                $date = array_slice(explode("-", $front), 1);
                array_push($list, implode("-", $date));
            }
        }
    }

    echo json_encode($list);
}

?>
