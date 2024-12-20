<?php
class Authentication{

    protected $pdo;


    public function __construct(\PDO $pdo){
        $this->pdo = $pdo;
    }

    public function isAuthorized(){
        //compare request token to db token
        $headers = array_change_key_case(getallheaders(),CASE_LOWER);
        return $this->getToken() === $headers['authorization'];
    }

    private function getToken(){
        $headers = array_change_key_case(getallheaders(),CASE_LOWER);

        $sqlString = "SELECT token FROM credentials WHERE username=?";
        try{
            $stmt = $this->pdo->prepare($sqlString);
            $stmt->execute([$headers['x-auth-user']]);
            $result = $stmt->fetchAll()[0];
            return $result['token'];
        }
        catch(Exception $e){
            echo $e->getMessage();
        }
        return "";
    }

    private function generateHeader(){
        $header = [
            "typ" => "JWT",
            "alg" => "HS256",
            "app" => "TournamentManagement",
            "dev" => "Markuz"
        ];
        return base64_encode(json_encode($header));
    }

    private function generatePayload($id, $username){
        $payload = [
            "uid" => $id,
            "uc" => $username,
            "email" => "Zakizakizaki@gmail.com",
            "date" => date_create(),
            "exp" => date("Y-m-d H-i-s")
        ];
        return base64_encode(json_encode($payload));
    }

    private function generateToken($id, $username){
        $header = $this->generateHeader();
        $payload = $this->generatePayload($id, $username);
        $signature = hash_hmac("sha256", "$header.$payload", TOKEN_KEY);
        
        // Return only the signature encoded in base64
        return base64_encode($signature);
    }
    

    private function isSamePassword($inputPassword, $existingHash){
        $hash = crypt($inputPassword, $existingHash);
        return $hash === $existingHash;
    }

    private function encryptPassword($password){
        $hashFormat = "$2y$10$";
        $saltLength = 22;
        $salt = $this->generateSalt($saltLength);
        return crypt($password, $hashFormat . $salt);
    }

    public function saveToken($token, $username){
        $errmsg = "";
        $code = 0;
    
        try{
            $sqlString = "UPDATE credentials SET token=? WHERE username = ?";
            $sql = $this->pdo->prepare($sqlString);
            $sql->execute([$token, $username]);
    
            $code = 200;
            $data = null;
    
            return array("data" => $data, "code" => $code);
        }
        catch(\PDOException $e){
            $errmsg = $e->getMessage();
            $code = 400;
        }
    
        return array("errmsg" => $errmsg, "code" => $code);
    }

    private function generateSalt($length){
        $urs = md5(uniqid(mt_rand(), true));
        $b64String = base64_encode($urs);
        $mb64String = str_replace("+", ".", $b64String);
        return substr($mb64String, 0, $length);
    }
    public function login($body) {
        if (!$body || !isset($body['username']) || !isset($body['password'])) {
            return [
                "payload" => null,
                "remarks" => "failed",
                "message" => "Username and password are required.",
                "code" => 400
            ];
        }
    
        $username = $body['username'];
        $password = $body['password'];
    
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM credentials WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    // Generate a new token
                    $token = $this->generateToken($user['id'], $username);
                    $this->saveToken($token, $username);
    
                    return [
                        "payload" => ["token" => $token],
                        "remarks" => "success",
                        "message" => "Login successful.",
                        "code" => 200
                    ];
                } else {
                    return [
                        "payload" => null,
                        "remarks" => "failed",
                        "message" => "Invalid password.",
                        "code" => 401
                    ];
                }
            } else {
                return [
                    "payload" => null,
                    "remarks" => "failed",
                    "message" => "Username does not exist.",
                    "code" => 401
                ];
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                "payload" => null,
                "remarks" => "failed",
                "message" => "Internal server error.",
                "code" => 500
            ];
        }
    }
    
    public function getRole() {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    
        if (isset($headers['x-auth-user']) && isset($headers['authorization'])) {
            $username = $headers['x-auth-user'];
            $token = $headers['authorization'];
    
            try {
                $stmt = $this->pdo->prepare("SELECT role FROM credentials WHERE username = :username AND token = :token");
                $stmt->execute(['username' => $username, 'token' => $token]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
                if ($result) {
                    return $result['role'];
                }
            } catch (Exception $e) {
                error_log("Error fetching role: " . $e->getMessage());
            }
        }
        return null;
    }

    public function hasPermission($requiredRole) {
        $role = $this->getRole();
        $roleHierarchy = ['user' => 1, 'teamleader' => 2, 'admin' => 3];
        
        return isset($roleHierarchy[$role]) && $roleHierarchy[$role] >= $roleHierarchy[$requiredRole];
    }
    


    public function addAccount($body){
        $values = [];
        $errmsg = "";
        $code = 0;
    
        // Encrypt password
        $body['password'] = $this->encryptPassword($body['password']);
    
        // Gather values for SQL
        foreach($body as $value){
            array_push($values, $value);
        }
    
        try{
            $sqlString = "INSERT INTO credentials(name, username, password) VALUES (?,?,?)";
            $sql = $this->pdo->prepare($sqlString);
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