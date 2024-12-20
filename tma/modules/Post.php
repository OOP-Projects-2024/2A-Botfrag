<?php

include_once "Common.php";
include_once "Auth.php";

class Post extends Common {
    protected $pdo;
    protected $auth;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
        $this->auth = new Authentication($pdo); // Initialize Authentication instance
    }

    public function postTeams($body) {
        if (is_array($body)) {
            $body = (object) $body;
        }
    
        $body = (object) array_change_key_case((array) $body, CASE_LOWER); // Normalize keys to lowercase
    
        $this->pdo->beginTransaction();
        try {
            if (empty($body->teamname) || empty($body->teamlocation) || empty($body->teamleader) || !is_array($body->member_ids)) {
                throw new \Exception("Invalid payload. Check your JSON structure.");
            }
    
            $memberIds = implode(",", $body->member_ids);
    
            $teamResult = $this->postData("teams", [
                "Teamname" => $body->teamname,
                "Teamlocation" => $body->teamlocation,
                "Teamleader" => $body->teamleader,
                "member_id" => $memberIds
            ], $this->pdo);
    
            if ($teamResult['code'] !== 200) {
                throw new \Exception($teamResult['errmsg']);
            }
    
            $teamId = $this->pdo->lastInsertId();
    
            $teamMemberSql = "INSERT INTO team_members (team_id, player_id) VALUES (?, ?)";
            $teamMemberStmt = $this->pdo->prepare($teamMemberSql);
            foreach ($body->member_ids as $playerId) {
                $teamMemberStmt->execute([$teamId, $playerId]);
            }
    
            $this->pdo->commit();
            // Call getLoggedInUsername from the Common class
            $this->logger(parent::getLoggedInUsername(), "POST", "Created a new team record with members.");
            return $this->generateResponse($teamResult['data'], "success", "Successfully created a new team record.", 201);
    
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            // Call getLoggedInUsername from the Common class
            $this->logger(parent::getLoggedInUsername(), "POST", $e->getMessage());
            return $this->generateResponse(null, "failed", $e->getMessage(), 400);
        }
    }
    
    public function postMatchmaking($body) {
        if (is_array($body)) {
            $body = (object) $body;
        }
        
        $body = (object) array_change_key_case((array) $body, CASE_LOWER);
        
        if (empty($body->round_name) || empty($body->matches) || !is_array($body->matches)) {
            return $this->generateResponse(null, "failed", "Invalid payload. Check round_name and matches.", 400);
        }
    
        $this->pdo->beginTransaction();
        try {
            // Insert round
            $roundData = [
                "round_name" => $body->round_name,
                "type" => $body->type ?? 'elimination'
            ];
            $roundResult = $this->postData("match_rounds", $roundData, $this->pdo);
            
            if ($roundResult['code'] !== 200) {
                throw new \Exception($roundResult['errmsg']);
            }
    
            $roundId = $this->pdo->lastInsertId();
    
            // Insert matches
            $matchInsert = "INSERT INTO matchups (round_id, participant1_id, participant2_id, participant3_id, participant4_id, participant5_id, participant6_id, match_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $matchStmt = $this->pdo->prepare($matchInsert);
    
            foreach ($body->matches as $match) {
                $matchStmt->execute([
                    $roundId,
                    $match['participant1_id'] ?? null,
                    $match['participant2_id'] ?? null,
                    $match['participant3_id'] ?? null,
                    $match['participant4_id'] ?? null,
                    $match['participant5_id'] ?? null,
                    $match['participant6_id'] ?? null,
                    $match['description']
                ]);
            }
    
            $this->pdo->commit();
            $this->logger(parent::getLoggedInUsername(), "POST", "Matchmaking round created successfully.");
            return $this->generateResponse($roundResult['data'], "success", "Matchmaking round created successfully.", 201);
    
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger(parent::getLoggedInUsername(), "POST", $e->getMessage());
            return $this->generateResponse(null, "failed", $e->getMessage(), 400);
        }
    }
    
    public function postWinners($body) {
        if (is_array($body)) {
            $body = (object) $body;
        }
    
        $body = (object) array_change_key_case((array) $body, CASE_LOWER);
    
        if (empty($body->match_id) || empty($body->winners) || !is_array($body->winners)) {
            return $this->generateResponse(null, "failed", "'match_id' and 'winners' array are required.", 400);
        }
    
        $this->pdo->beginTransaction();
        try {
            $matchId = $body->match_id;
            $winners = $body->winners;
    
            foreach ($winners as $winner) {
                $playerIds = is_array($winner['player_id']) ? $winner['player_id'] : [$winner['player_id']];
    
                // Validate player IDs
                $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
                $checkPlayerSql = "SELECT COUNT(*) FROM players WHERE id IN ($placeholders)";
                $checkPlayerStmt = $this->pdo->prepare($checkPlayerSql);
                $checkPlayerStmt->execute($playerIds);
    
                if ($checkPlayerStmt->fetchColumn() < count($playerIds)) {
                    throw new \Exception("One or more player IDs do not exist.");
                }
    
                // Insert winners
                $winnerInsert = "INSERT INTO event_winners (match_id, winner_team_id, winner_player_id) VALUES (?, ?, ?)";
                $winnerStmt = $this->pdo->prepare($winnerInsert);
    
                foreach ($playerIds as $playerId) {
                    $winnerStmt->execute([$matchId, $winner['team_id'] ?? null, $playerId]);
                }
            }
    
            $this->pdo->commit();
            $this->logger(parent::getLoggedInUsername(), "POST", "Winners recorded for match ID: $matchId.");
            return $this->generateResponse($winners, "success", "Winners recorded successfully.", 201);
    
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger(parent::getLoggedInUsername(), "POST", $e->getMessage());
            return $this->generateResponse(null, "failed", $e->getMessage(), 400);
        }
    }
    

    
    
    
    

    
    public function matchTeams($body) {
        // Ensure input is an object
        if (is_array($body)) {
            $body = (object) $body;
        }
    
        // Validate required fields
        if (empty($body->teams) || !is_array($body->teams)) {
            return $this->generateResponse(null, "failed", "'teams' array is required.", 400);
        }
    
        $teams = $body->teams;
        $eventName = $body->event_name ?? "Unnamed Event";
        $criteria = $body->criteria ?? "random";
    
        // Shuffle teams if criteria is random
        if ($criteria === "random") {
            shuffle($teams);
        }
    
        // Pair teams
        $pairs = [];
        while (count($teams) > 1) {
            $team1 = array_shift($teams);
            $team2 = array_shift($teams);
            $pairs[] = ["team1_id" => $team1, "team2_id" => $team2];
        }
    
        // Store matches in the database
        foreach ($pairs as $pair) {
            $data = [
                "team1_id" => $pair['team1_id'],
                "team2_id" => $pair['team2_id'],
                "event_name" => $eventName
            ];
    
            $result = $this->postData("matches", $data, $this->pdo);
    
            if ($result['code'] !== 200) {
                return $this->generateResponse(
                    null,
                    "failed",
                    "Failed to save matches.",
                    500
                );
            }
        }
    
        $this->logger(parent::getLoggedInUsername(), "POST", "Matches created for event: $eventName.");
        return $this->generateResponse($pairs, "success", "Matches created successfully.", 201);
    }
    

    public function postPlayer($body) {
        if (is_array($body)) {
            $body = (object) $body;
        }
    
        $body = (object) array_change_key_case((array) $body, CASE_LOWER); // Normalize keys to lowercase
    
        if (empty($body->ign) || empty($body->role)) {
            return $this->generateResponse(null, "failed", "IGN and Role are required fields.", 400);
        }
    
        $result = $this->postData("players", [
            "ign" => $body->ign,
            "role" => $body->role
        ], $this->pdo);
    
        if ($result['code'] == 200) {
            $this->logger(parent::getLoggedInUsername(), "POST", "Created a new player record.");
            return $this->generateResponse($result['data'], "success", "Successfully created a new player record.", 201);
        }
    
        $this->logger(parent::getLoggedInUsername(), "POST", $result['errmsg']);
        return $this->generateResponse(null, "failed", $result['errmsg'], $result['code']);
    }
    
}
?>
