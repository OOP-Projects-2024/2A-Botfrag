<?php
include_once "Common.php";

class Get extends Common {
    protected $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getLogs($date) {
        $directory = 'C:/CCS/htdocs/tma/logs/';
        $filename = $directory . $this->getLogFileName($date);
        $logs = [];

        try {
            $username = $this->getLoggedInUsername();
            $this->logger($username, "GET", "Attempting to retrieve logs for date: $date");

            $file = new SplFileObject($filename);
            while (!$file->eof()) {
                $logs[] = $file->fgets();
            }

            $remarks = "success";
            $message = "Successfully retrieved logs.";
            $this->logger($username, "GET", "Successfully retrieved logs for date: $date");
        } catch (Exception $e) {
            $remarks = "failed";
            $message = $e->getMessage();
            $this->logger($username, "GET", "Error retrieving logs for date: $date - " . $message);
        }

        return $this->generateResponse(['logs' => $logs], $remarks, $message, 200);
    }

    public function getMatchTeams($eventName = null) {
        $sqlString = "SELECT 
                        matches.id AS match_id,
                        matches.team1_id,
                        matches.team2_id,
                        teams1.Teamname AS team1_name,
                        teams2.Teamname AS team2_name,
                        matches.event_name
                    FROM 
                        matches
                    LEFT JOIN teams AS teams1 ON matches.team1_id = teams1.id
                    LEFT JOIN teams AS teams2 ON matches.team2_id = teams2.id
                    WHERE 
                        matches.isdeleted = 0"; // Exclude archived matches
    
        // Add event name filter if provided
        if ($eventName !== null) {
            $sqlString .= " AND matches.event_name = :eventName";
        }
    
        try {
            $stmt = $this->pdo->prepare($sqlString);
            if ($eventName !== null) {
                $stmt->execute([':eventName' => $eventName]);
            } else {
                $stmt->execute();
            }
    
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            if ($result) {
                return $this->generateResponse($result, "success", "Successfully retrieved match teams.", 200);
            }
    
            return $this->generateResponse(null, "failed", "No matches found.", 404);
    
        } catch (\Exception $e) {
            $this->logger($this->getLoggedInUsername(), "GET", "Error retrieving match teams: " . $e->getMessage());
            return $this->generateResponse(null, "failed", $e->getMessage(), 500);
        }
    }
    
    
    
    public function getMatchmakeById($matchId) {
        $sqlString = "SELECT 
                        matches.id AS match_id,
                        matches.team1_id,
                        matches.team2_id,
                        teams1.Teamname AS team1_name,
                        teams2.Teamname AS team2_name,
                        matches.event_name,
                        matches.isdeleted
                    FROM 
                        matches
                    LEFT JOIN teams AS teams1 ON matches.team1_id = teams1.id
                    LEFT JOIN teams AS teams2 ON matches.team2_id = teams2.id
                    WHERE 
                        matches.id = :matchId AND
                        matches.isdeleted = 0"; // Ensure only non-deleted matches are retrieved
    
        try {
            $stmt = $this->pdo->prepare($sqlString);
            $stmt->execute([':matchId' => $matchId]);
    
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($result) {
                return $this->generateResponse($result, "success", "Successfully retrieved match details.", 200);
            }
    
            return $this->generateResponse(null, "failed", "No match found with the provided ID.", 404);
    
        } catch (\Exception $e) {
            $this->logger($this->getLoggedInUsername(), "GET", "Error retrieving match details for ID: $matchId - " . $e->getMessage());
            return $this->generateResponse(null, "failed", $e->getMessage(), 500);
        }
    }
    

    public function getPlayers() {
        $condition = "isdeleted = 0";
        $result = $this->getDataByTable('players', $condition, $this->pdo);

        if ($result['code'] === 200) {
            return $this->generateResponse($result['data'], "success", "Successfully retrieved records.", $result['code']);
        }

        return $this->generateResponse(null, "failed", $result['errmsg'], $result['code']);
    }

    public function getTeams() {
        $sqlString = "SELECT 
                        teams.id, 
                        teams.Teamname, 
                        teams.Teamlocation, 
                        teams.Teamleader
                    FROM 
                        teams
                    WHERE
                        teams.isdeleted = 0"; // Exclude deleted teams
        
        $username = $this->getLoggedInUsername();
        $this->logger($username, "GET", "Retrieved teams data");

        $result = $this->getDataBySQL($sqlString, $this->pdo);
        if ($result['code'] === 200) {
            return $this->generateResponse($result['data'], "success", "Successfully retrieved records.", $result['code']);
        }

        return $this->generateResponse(null, "failed", $result['errmsg'], $result['code']);
    }

    public function getPlayerid($id = null) {
        $condition = "isdeleted=0";
        if ($id !== null) {
            $condition .= " AND players.id = " . $id;
        }

        $result = $this->getDataByTable('players', $condition, $this->pdo);
        if ($result['code'] === 200) {
            return $this->generateResponse($result['data'], "success", "Successfully retrieved records.", $result['code']);
        }

        return $this->generateResponse(null, "failed", $result['errmsg'], $result['code']);
    }

    public function getTeamid($teamId) {
        $sqlString = "SELECT 
                        teams.id, 
                        teams.Teamname, 
                        teams.Teamlocation, 
                        teams.Teamleader
                    FROM 
                        teams
                    WHERE 
                        teams.id = :teamId AND
                        teams.isdeleted = 0"; // Exclude deleted teams
        
        $username = $this->getLoggedInUsername();
        $this->logger($username, "GET", "Retrieved team data for team ID: $teamId");

        $stmt = $this->pdo->prepare($sqlString);
        $stmt->execute([':teamId' => $teamId]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result) {
            return $this->generateResponse($result, "success", "Successfully retrieved records.", 200);
        }

        return $this->generateResponse(null, "failed", "No records found.", 404);
    }
}
?>
