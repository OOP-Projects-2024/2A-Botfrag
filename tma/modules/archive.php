<?php
include_once "Common.php";
class Archive extends Common {
    protected $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function deleteMatchId($matchId) {
        try {
            // Check if there are any winners associated with the match before deleting it
            $checkWinners = "SELECT COUNT(*) FROM event_winners WHERE match_id = ?";
            $checkStmt = $this->pdo->prepare($checkWinners);
            $checkStmt->execute([$matchId]);
            $winnerCount = $checkStmt->fetchColumn();
    
            if ($winnerCount > 0) {
                return $this->generateResponse(null, "failed", "Match with ID $matchId cannot be deleted because it has associated winners.", 400);
            }
    
            // Proceed to delete the match if there are no associated winners
            $deleteMatch = "DELETE FROM matches WHERE id = ?";
            $deleteStmt = $this->pdo->prepare($deleteMatch);
            $deleteStmt->execute([$matchId]);
    
            if ($deleteStmt->rowCount() > 0) {
                $this->logger(parent::getLoggedInUsername(), "DELETE", "Match with ID $matchId deleted successfully.");
                return $this->generateResponse(null, "success", "Match with ID $matchId deleted successfully.", 200);
            } else {
                return $this->generateResponse(null, "failed", "No match found with ID $matchId or it was already deleted.", 404);
            }
        } catch (\Exception $e) {
            $this->logger(parent::getLoggedInUsername(), "DELETE", $e->getMessage());
            return $this->generateResponse(null, "failed", "Error deleting match: " . $e->getMessage(), 500);
        }
    }
    
    
    

    public function deletePlayer($id) {
        $errmsg = "";
        $code = 0;
        $username = $this->getLoggedInUsername(); // Fetch the logged-in username

        try {
            $sqlString = "UPDATE players SET isdeleted = 1 WHERE id = ?";
            $sql = $this->pdo->prepare($sqlString);
            $sql->execute([$id]);

            if ($sql->rowCount() > 0) {
                $code = 200; 
                $data = ["message" => "Player marked as deleted successfully"];
                $this->logger($username, "DELETE", "Player with ID $id marked as deleted.");
            } else {
                $code = 404; 
                $data = ["error" => "Player not found or already marked as deleted"];
                $this->logger($username, "DELETE", "Failed to find or mark player with ID $id as deleted.");
            }

            return array("data" => $data, "code" => $code);
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            $code = 400; 
            $this->logger($username, "DELETE", "Error deleting player with ID $id: $errmsg");

            return array("errmsg" => $errmsg, "code" => $code);
        }
    }

    public function destroyPlayer($id) {
        $errmsg = "";
        $code = 0;
        $username = $this->getLoggedInUsername(); // Fetch the logged-in username

        try {
            $sqlString = "DELETE FROM players WHERE id = ?";
            $sql = $this->pdo->prepare($sqlString);
            $sql->execute([$id]);

            if ($sql->rowCount() > 0) {
                $code = 200; 
                $data = ["message" => "Player deleted successfully"];
                $this->logger($username, "DELETE", "Player with ID $id permanently deleted.");
            } else {
                $code = 404; 
                $data = ["error" => "Player not found"];
                $this->logger($username, "DELETE", "Failed to find player with ID $id for deletion.");
            }

            return array("data" => $data, "code" => $code);
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            $code = 400; 
            $this->logger($username, "DELETE", "Error permanently deleting player with ID $id: $errmsg");

            return array("errmsg" => $errmsg, "code" => $code);
        }
    }

    public function deleteMatchTeams($matchId) {
        try {
            $username = $this->getLoggedInUsername(); // Fetch the logged-in username
    
            // Mark the match teams as archived (isdeleted = 1)
            $sqlString = "UPDATE matches SET isdeleted = 1 WHERE id = ?";
            $stmt = $this->pdo->prepare($sqlString);
            $stmt->execute([$matchId]);
    
            if ($stmt->rowCount() > 0) {
                $this->logger($username, "ARCHIVE", "Match teams with Match ID $matchId marked as archived.");
                return $this->generateResponse(null, "success", "Match teams with ID $matchId archived successfully.", 200);
            } else {
                return $this->generateResponse(null, "failed", "Match teams not found or already archived.", 404);
            }
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            $this->logger($this->getLoggedInUsername(), "ARCHIVE", "Error archiving match teams for Match ID $matchId: $errmsg");
            return $this->generateResponse(null, "failed", "Error archiving match teams: $errmsg", 500);
        }
    }
    
    public function destroyMatchTeams($matchId) {
        try {
            $username = $this->getLoggedInUsername(); // Fetch the logged-in username
    
            // Permanently delete match teams
            $sqlString = "DELETE FROM matches WHERE id = ?";
            $stmt = $this->pdo->prepare($sqlString);
            $stmt->execute([$matchId]);
    
            if ($stmt->rowCount() > 0) {
                $this->logger($username, "DELETE", "Match teams with Match ID $matchId permanently deleted.");
                return $this->generateResponse(null, "success", "Match teams with ID $matchId permanently deleted.", 200);
            } else {
                return $this->generateResponse(null, "failed", "Match teams not found.", 404);
            }
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            $this->logger($this->getLoggedInUsername(), "DELETE", "Error deleting match teams for Match ID $matchId: $errmsg");
            return $this->generateResponse(null, "failed", "Error deleting match teams: $errmsg", 500);
        }
    }
    

    public function deleteTeamid($id) {
        $errmsg = "";
        $code = 0;
        $username = $this->getLoggedInUsername(); // Fetch the logged-in username

        try {
            $sqlString = "UPDATE teams SET isdeleted = 1 WHERE id = ?";
            $sql = $this->pdo->prepare($sqlString);
            $sql->execute([$id]);

            if ($sql->rowCount() > 0) {
                $code = 200;
                $data = ["message" => "Team marked as deleted successfully"];
                $this->logger($username, "DELETE", "Team with ID $id marked as deleted.");
            } else {
                $code = 404;
                $data = ["error" => "Team not found or already marked as deleted"];
                $this->logger($username, "DELETE", "Failed to find or mark team with ID $id as deleted.");
            }

            return array("data" => $data, "code" => $code);
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            $code = 400;
            $this->logger($username, "DELETE", "Error marking team with ID $id as deleted: $errmsg");

            return array("errmsg" => $errmsg, "code" => $code);
        }
    }

    public function destroyTeamid($id) {
        $errmsg = "";
        $code = 0;
        $username = $this->getLoggedInUsername(); // Fetch the logged-in username

        try {
            $sqlString = "DELETE FROM teams WHERE id = ?";
            $sql = $this->pdo->prepare($sqlString);
            $sql->execute([$id]);

            if ($sql->rowCount() > 0) {
                $code = 200;
                $data = ["message" => "Team deleted successfully"];
                $this->logger($username, "DELETE", "Team with ID $id permanently deleted.");
            } else {
                $code = 404;
                $data = ["error" => "Team not found"];
                $this->logger($username, "DELETE", "Failed to find team with ID $id for deletion.");
            }

            return array("data" => $data, "code" => $code);
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            $code = 400;
            $this->logger($username, "DELETE", "Error permanently deleting team with ID $id: $errmsg");

            return array("errmsg" => $errmsg, "code" => $code);
        }
    }

}
?>
