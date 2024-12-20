<?php
class Common{
    public function prettyPrint($data) {
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    protected function getLogFileName($date = null) {
        $date = $date ?? date("Y-m-d"); // Default to today's date if no date is provided
        return $date . ".log";
    }
    
    protected function getLoggedInUsername() {
        // Ensure the token is available from the request headers
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        if (!isset($headers['authorization'])) {
            return 'Guest'; // Default fallback if no token is found
        }
    
        $token = $headers['authorization'];
    
        // Query the database to fetch the user's name based on the token
        $query = "SELECT name FROM credentials WHERE token = ?";
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$token]);
            $result = $stmt->fetch();
    
            // Return the user's name if found, or fallback to 'Guest'
            return $result['name'] ?? 'Guest';
        } catch (\PDOException $e) {
            // Log or handle any database error
            error_log($e->getMessage());
        }
    
        return 'Guest';
    }
    
    

    protected function logger($user, $method, $action){
        $filename = date("Y-m-d") . ".log";
        $datetime = date("Y-m-d H:i:s");
        $logMessage = "$datetime,$method,$user,$action" . PHP_EOL;
        error_log($logMessage, 3, "C:/CCS/htdocs/tma/logs/$filename");
    }

    private function generateInsertString($tablename, $body){
        $keys = array_keys($body);
        $fields = implode(",", $keys);
        $parameter_array = [];
        for($i = 0; $i < count($keys); $i++){
            $parameter_array[$i] = "?";
        }
        $parameters = implode(',', $parameter_array);
        $sql = "INSERT INTO $tablename($fields) VALUES ($parameters)";
        return $sql;
    }

    protected function getDataByTable($tableName, $condition, \PDO $pdo){
        $sqlString = "SELECT id, ign, role FROM $tableName WHERE $condition";

        $data = array();
        $errmsg = "";
        $code = 0;


        try{
            if($result = $pdo->query($sqlString)->fetchAll()){
                foreach($result as $record){
                    array_push($data, $record);
                }
                $result = null;
                $code = 200;
                return array("code"=>$code, "data"=>$data); 
            }
            else{
                $errmsg = "No data found";
                $code = 404;
            }
        }
        catch(\PDOException $e){
            $errmsg = $e->getMessage();
            $code = 403;
        }

        return array("code"=>$code, "errmsg"=>$errmsg);
    }

    protected function getDataBySQL($sqlString, \PDO $pdo){
        $data = array();
        $errmsg = "";
        $code = 0;


        try{
            if($result = $pdo->query($sqlString)->fetchAll()){
                foreach($result as $record){
                    array_push($data, $record);
                }
                $result = null;
                $code = 200;
                return array("code"=>$code, "data"=>$data); 
            }
            else{
                $errmsg = "No data found";
                $code = 404;
            }
        }
        catch(\PDOException $e){
            $errmsg = $e->getMessage();
            $code = 403;
        }

        return array("code"=>$code, "errmsg"=>$errmsg);
    }


    public function generateResponse($data, $remark, $message, $statusCode){
        $status = array(
            "remark" => $remark,
            "message" => $message
        );

        http_response_code($statusCode);

        return array(
            "payload" => $data,
            "status" => $status,
            "prepared_by" => "Markuz", 
            "date_generated" => date_create()
        );
    }

    public function postData($tableName, $body, \PDO $pdo){
        $values = [];
        $errmsg = "";
        $code = 0;


        foreach($body as $value){
            array_push($values, $value);
        }

        try{
            $sqlString = $this->generateInsertString($tableName, $body);
            $sql = $pdo->prepare($sqlString);
            $sql->execute($values);

            $code = 200;
            $data = null;

            return array("data"=>$data, "code"=>$code);
        }
        catch(\PDOException $e){
            $errmsg = $e->getMessage();
            $code = 400;
        }


        return array("errmsg"=>$errmsg, "code"=>$code);

    }

}


?>