<?php
include_once "Common.php";
class Patch extends Common{

    protected $pdo;

    public function __construct(\PDO $pdo){
        $this->pdo = $pdo;
    }
 
    public function patchMatchmake($matchId, $data) {
        $username = $this->getLoggedInUsername(); // Log the username for auditing
    
        try {
            // Validate input data
            if (empty($matchId)) {
                return ["error" => "Match ID is required.", "code" => 400];
            }
    
            // Prepare the update fields and parameters
            $updateFields = [];
            $params = [];
    
            if (!empty($data->team1_id)) {
                $updateFields[] = "team1_id = :team1_id";
                $params[':team1_id'] = $data->team1_id;
            }
    
            if (!empty($data->team2_id)) {
                $updateFields[] = "team2_id = :team2_id";
                $params[':team2_id'] = $data->team2_id;
            }
    
            if (!empty($data->event_name)) {
                $updateFields[] = "event_name = :event_name";
                $params[':event_name'] = $data->event_name;
            }
    
            if (!empty($data->match_date)) {
                $updateFields[] = "match_date = :match_date";
                $params[':match_date'] = $data->match_date;
            }
    
            // Add handling for isdeleted field
            if (isset($data->isdeleted)) { // Use isset to allow for 0 as a valid value
                $updateFields[] = "isdeleted = :isdeleted";
                $params[':isdeleted'] = $data->isdeleted;
            }
    
            // Ensure we have fields to update
            if (count($updateFields) === 0) {
                return ["error" => "No valid fields provided for update.", "code" => 400];
            }
    
            // Build and execute the update query
            $query = "UPDATE matches SET " . implode(", ", $updateFields) . " WHERE id = :match_id";
            $params[':match_id'] = $matchId;
    
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
    
            // Log the success
            $this->logger($username, "PATCH", "Successfully updated match with ID: $matchId");
    
            return ["message" => "Match updated successfully.", "code" => 200];
        } catch (\Exception $e) {
            $errmsg = $e->getMessage();
    
            // Log the error
            $this->logger($username, "PATCH", "Error updating match with ID: $matchId - $errmsg");
    
            return ["error" => "An error occurred while updating the match: " . $errmsg, "code" => 400];
        }
    }

    
    public function updateTeamMembers($teamId, $playerIds) {
        try {
            // Remove all existing team members for the team
            $deleteQuery = "DELETE FROM team_members WHERE team_id = :teamid";
            $deleteStmt = $this->pdo->prepare($deleteQuery);
            $deleteStmt->execute([':teamid' => $teamId]);
    
            // Insert the new player IDs into the team_members table
            $insertQuery = "INSERT INTO team_members (team_id, player_id) VALUES (:teamid, :playerid)";
            $insertStmt = $this->pdo->prepare($insertQuery);
    
            foreach ($playerIds as $playerId) {
                $insertStmt->execute([
                    ':teamid' => $teamId,
                    ':playerid' => $playerId
                ]);
            }
    
            return ["message" => "Team members updated successfully"];
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
            return ["error" => "An error occurred while updating team members: " . $errmsg];
        }
    }
    
    
    

    
    public function updateTeam($data, $teamId) {
        $username = $this->getLoggedInUsername();
    
        try {
            // Prepare the update fields and parameters
            $updateFields = [];
            $params = [];
            
            if (!empty($data->teamname)) {
                $updateFields[] = "teamname = :teamname";
                $params[':teamname'] = $data->teamname;
            }
    
            if (!empty($data->teamlocation)) {
                $updateFields[] = "teamlocation = :teamlocation";
                $params[':teamlocation'] = $data->teamlocation;
            }
    
            if (!empty($data->teamleader)) {
                $updateFields[] = "teamleader = :teamleader";
                $params[':teamleader'] = $data->teamleader;
            }
    
            if (!empty($data->member_id)) {
                // Convert array of member IDs to a comma-separated string
                $memberIds = is_array($data->member_id) ? implode(',', $data->member_id) : $data->member_id;
                $updateFields[] = "member_id = :member_id";
                $params[':member_id'] = $memberIds;
            }
    
            // If fields exist, update the teams table
            if (count($updateFields) > 0) {
                $query = "UPDATE teams SET " . implode(", ", $updateFields) . " WHERE id = :teamid";
                $params[':teamid'] = $teamId;
    
                $stmt = $this->pdo->prepare($query);
                $stmt->execute($params);
            }
    
            return ["message" => "Team updated successfully"];
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
            return ["error" => "An error occurred while updating the team: " . $errmsg];
        }
    }
    
    
    
    public function deleteTeam($teamId, $isdeleted) {
        $username = $this->getLoggedInUsername();

        try {
            if (!in_array($isdeleted, [0, 1])) {
                return ["error" => "Invalid value for isdeleted. Must be 0 or 1."];
            }

            // Log the attempt to update deletion status
            $this->logger($username, "PATCH", "Attempting to update deletion status of team with ID: $teamId to isdeleted = $isdeleted");

            // Update the isdeleted status
            $query = "UPDATE teams SET isdeleted = :isdeleted WHERE id = :teamid";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':isdeleted' => $isdeleted, ':teamid' => $teamId]);

            // Log the success
            $this->logger($username, "PATCH", "Successfully updated deletion status of team with ID: $teamId");

            return ["message" => "Team deletion status updated successfully"];
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
            // Log failure
            $this->logger($username, "PATCH", "Error updating deletion status of team with ID: $teamId - $errmsg");
            return ["error" => "An error occurred while updating the deletion status: " . $errmsg];
        }
    }

    public function destroyTeam()
    {
        echo "To permanently delete a team, \ngo to DELETE method and use destroyteamid/<idhere>.\n";
    }

    public function destroyPlayer()
    {
        echo "To permanently delete a team, \ngo to DELETE method and use destroyplayer/<idhere>.\n";
    }

    public function updatePlayer($body, $id) {
        $username = $this->getLoggedInUsername(); // Ensure this method is available
        $values = [];
        $errmsg = "";
        $code = 0;
    
        // Construct the values array with the required fields (ign and role only)
        if (!empty($body->ign)) {
            $values[] = $body->ign;
        }
    
        if (!empty($body->role)) {
            $values[] = $body->role;
        }
    
        // Add player ID at the end
        $values[] = $id;
    
        try {
            // Log before updating
            $this->logger($username, "PATCH", "Attempting to update player with ID: $id");
    
            // Prepare the SQL query (no isdeleted field)
            $sqlString = "UPDATE players SET ign=?, role=? WHERE id = ?";
            $sql = $this->pdo->prepare($sqlString);
            $sql->execute($values);
    
            // Log success
            $this->logger($username, "PATCH", "Successfully updated player with ID: $id");
    
            $code = 200;
            $message = "Player updated successfully."; 
            return array("message" => $message, "code" => $code);
        } catch (\PDOException $e) {
            $errmsg = $e->getMessage();
            $code = 400;
    
            // Log failure
            $this->logger($username, "PATCH", "Error updating player with ID: $id - $errmsg");
    
            return array("errmsg" => $errmsg, "code" => $code);
        }
    }


    public function updateDeletedPlayer($playerId, $isDeleted) {
        $username = $this->getLoggedInUsername(); // Get the logged-in username for logging purposes
    
        try {
            // Validate the value of isDeleted
            if (!in_array($isDeleted, [0, 1])) {
                return ["error" => "Invalid value for isdeleted. Must be 0 or 1."];
            }
    
            // Log the attempt to update deletion status
            $this->logger($username, "PATCH", "Attempting to update deletion status of player with ID: $playerId to isdeleted = $isDeleted");
    
            // Update the isDeleted status in the database
            $query = "UPDATE players SET isdeleted = :isdeleted WHERE id = :playerid";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':isdeleted' => $isDeleted, ':playerid' => $playerId]);
    
            // Check if the update affected any rows
            if ($stmt->rowCount() > 0) {
                // Log the success
                $this->logger($username, "PATCH", "Successfully updated deletion status of player with ID: $playerId");
    
                return ["message" => "Player deletion status updated successfully", "code" => 200];
            } else {
                // Log the no-change case
                $this->logger($username, "PATCH", "No rows updated for player ID: $playerId. Check if the ID exists or the value is unchanged.");
    
                return ["error" => "No changes made. Player ID may not exist or isdeleted value is the same.", "code" => 400];
            }
        } catch (\Exception $e) {
            $errmsg = $e->getMessage();
            
            // Log the error
            $this->logger($username, "PATCH", "Error updating deletion status of player with ID: $playerId - $errmsg");
    
            return ["error" => "An error occurred while updating the deletion status: " . $errmsg, "code" => 400];
        }
    }
    
    
    
    
    
    }
    
    


?>