<?php

// Import dependencies
require_once "./config/database.php";
require_once "./modules/Get.php";
require_once "./modules/Post.php";
require_once "./modules/Patch.php";
require_once "./modules/Archive.php";
require_once "./modules/Auth.php";
require_once "./modules/Common.php";

$db = new Connection();
$pdo = $db->connect();

// Class instantiation
$post = new Post($pdo);
$get = new Get($pdo);
$patch = new Patch($pdo);
$archive = new Archive($pdo);
$auth = new Authentication($pdo);

// Retrieve request and split
if (isset($_REQUEST['request'])) {
    $request = explode("/", $_REQUEST['request']);
} else {
    echo "URL does not exist.";
    exit;
}

// Determine user role
$role = $auth->getRole();
if (!$role && !in_array($request[0], ["login", "signup"])) { // Allow access to login and signup without role check
    http_response_code(403);
    echo json_encode(["error" => "You do not have access to this function"]);
    exit;
}

// Handle different HTTP methods
switch ($_SERVER['REQUEST_METHOD']) {
    case "GET":
        if (in_array($role, ["user", "teamleader", "admin"])) {
            switch ($request[0]) {
                case "players":
                    $dataString = $get->getPlayers($request[1] ?? null);
                    echo $get->prettyPrint($dataString);
                    break;
                case "log":
                    $dataString = $get->getLogs($request[1] ?? null);
                    echo $get->prettyPrint($dataString);
                    break;
                case "playerid":
                    if (isset($request[1])) {
                        echo $get->prettyPrint($get->getPlayerid($request[1] ?? null));
                    } else {
                        http_response_code(400);
                        echo $get->prettyPrint(["error" => "Player ID is required"]);
                    }
                    break;
                case "matchteams":
                    if (isset($request[1])) { // Check if an ID is provided
                        echo $get->prettyPrint($get->getMatchmakeById($request[1]));
                    } else {
                        echo $get->prettyPrint($get->getMatchTeams());
                    }
                    break;
                case "teams":
                    echo $get->prettyPrint($get->getTeams());
                    break;
                case "teamid":
                    if (isset($request[1])) {
                        echo $get->prettyPrint($get->getTeamid($request[1]));
                    } else {
                        http_response_code(400);
                        echo $get->prettyPrint(["error" => "Team ID is required"]);
                    }
                    break;
                default:
                    http_response_code(404);
                    echo $get->prettyPrint(["error" => "Endpoint not found"]);
                    break;
            }
        } else {
            http_response_code(403);
            echo json_encode(["error" => "You do not have access to this function."]);
        }
        break;

    case "POST":
        $body = json_decode(file_get_contents("php://input"), true);
        
        // Handle login and signup separately, they are accessible regardless of role
        if ($request[0] === "login") {
            echo $get->prettyPrint($auth->login($body));
        } elseif ($request[0] === "signup") {
            echo $get->prettyPrint($auth->signup($body));
        } else {
            if (in_array($role, ["teamleader", "admin"])) {
                switch ($request[0]) {
                    case "team":
                        echo $get->prettyPrint($post->postTeams($body));
                        break;
                    case "player":
                        echo $get->prettyPrint($post->postPlayer($body));
                        break;
                    case "winners":
                        echo $get->prettyPrint($post->postWinners($body));
                        break;
                    case "matchmake":
                        echo $get->prettyPrint($post->matchTeams($body));
                        break;
                    default:
                        http_response_code(404);
                        echo $get->prettyPrint(["error" => "Endpoint not found"]);
                        break;
                }
            } else {
                http_response_code(403);
                echo json_encode(["error" => "You do not have access to this function."]);
            }
        }
        break;

    case "PATCH":
        $body = json_decode(file_get_contents("php://input"));
        if ($role === "admin") {
            switch ($request[0]) {
                case "teamid":
                    if (isset($request[1])) {
                        if (isset($request[2]) && $request[2] === 'delete') {
                            if (isset($body->isdeleted)) {
                                echo $get->prettyPrint($patch->deleteTeam($request[1], $body->isdeleted));
                            } else {
                                http_response_code(400);
                                echo $get->prettyPrint(["error" => "isdeleted field is required"]);
                            }
                        } elseif (isset($request[2]) && $request[2] === 'destroy') {
                            echo $get->prettyPrint($patch->destroyTeam($request[1]));
                        } else {
                            echo $get->prettyPrint($patch->updateTeam($body, $request[1]));
                        }
                    } else {
                        http_response_code(400);
                        echo $get->prettyPrint(["error" => "Team ID is required"]);
                    }
                    break;
                case "playerid":
                    if (isset($request[1])) {
                        if (isset($request[2]) && $request[2] === 'delete') {
                            if (isset($body->isdeleted)) {
                                echo $get->prettyPrint($patch->UpdateDeletedPlayer($request[1], $body->isdeleted));
                            } else {
                                http_response_code(400);
                                echo $get->prettyPrint(["error" => "isdeleted field is required"]);
                            }
                        } elseif (isset($request[2]) && $request[2] === 'destroy') {
                            echo $get->prettyPrint($patch->destroyPlayer($request[1]));
                        } else {
                            echo $get->prettyPrint($patch->updatePlayer($body, $request[1]));
                        }
                    } else {
                        http_response_code(400);
                        echo $get->prettyPrint(["error" => "Player ID is required"]);
                    }
                    break;
                case "matchteams":
                    if (isset($request[1])) {
                        $matchId = $request[1];
                        echo $get->prettyPrint($patch->patchMatchmake($matchId, $body));
                    } else {
                        http_response_code(400);
                        echo $get->prettyPrint(["error" => "Match ID is required"]);
                    }
                    break;
                default:
                    http_response_code(404);
                    echo $get->prettyPrint(["error" => "Endpoint not found"]);
                    break;
            }
        } else {
            http_response_code(403);
            echo json_encode(["error" => "You do not have access to this function."]);
        }
        break;

    case "DELETE":
        if ($role === "admin") {
            switch ($request[0]) {
                case "playerid":
                    echo $get->prettyPrint($archive->deletePlayer($request[1]));
                    break;
                case "destroyplayerid":
                    echo $get->prettyPrint($archive->destroyPlayer($request[1]));
                    break;
                case "teamid":
                    echo $get->prettyPrint($archive->deleteTeamid($request[1]));
                    break;
                case "destroyteamid":
                    echo $get->prettyPrint($archive->destroyTeamid($request[1]));
                    break;
                case "account":
                    echo $get->prettyPrint($archive->deleteAccount($request[1]));
                    break;
                case "destroyaccount":
                    echo $get->prettyPrint($archive->destroyAccount($request[1]));
                    break;
                case "matchid":
                    if (isset($request[1])) {
                        $matchId = $request[1];
                        $response = $archive->deleteMatchId($matchId);
                        echo $get->prettyPrint($response);
                    } else {
                        http_response_code(400);
                        echo $get->prettyPrint(["error" => "Match ID is required"]);
                    }
                    break;
                case "matchteams":
                    if (isset($request[1])) {
                        echo $get->prettyPrint($archive->deleteMatchTeams($request[1]));
                    } else {
                        http_response_code(400);
                        echo $get->prettyPrint(["error" => "Match Team ID is required"]);
                    }
                    break;
                case "destroymatchteams":
                    if (isset($request[1])) {
                        echo $get->prettyPrint($archive->destroyMatchTeams($request[1]));
                    } else {
                        http_response_code(400);
                        echo $get->prettyPrint(["error" => "Match Team ID is required"]);
                    }
                    break;
                default:
                    http_response_code(404);
                    echo $get->prettyPrint(["error" => "Endpoint not found"]);
                    break;
            }
        } else {
            http_response_code(403);
            echo json_encode(["error" => "You do not have access to this function."]);
        }
        break;

    default:
        http_response_code(400);
        echo $get->prettyPrint(["error" => "Invalid Request Method"]);
        break;
}

$pdo = null;

?>
