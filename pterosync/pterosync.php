<?php
//TODO handle when egg_id and nest_id is changed
//TODO make it optional to reinstall the server when egg_id or nest_id is changed or both.
//TODO make so the client can change the product from given list.
//Example: Each product will have an option to select products
//Where user can choose from those products to Swap/Change
//This will be usefull if you want to allow users to change to different product with same price.
//TODO add also option for time so they don't spam it.

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

global $_LANG;

$language = $_SESSION['Language'] ?? 'english';
// Load language file based on the client's language preference
if (file_exists(dirname(__FILE__) . '/lang/' . $language . '.php')) {
    include dirname(__FILE__) . '/lang/' . $language . '.php';
} else {
    include dirname(__FILE__) . '/lang/english.php'; // Fallback to English
}
$_LANG = array_merge($keys, $_LANG);


include_once dirname(__FILE__) . '/helper.php';


/*
 * Module PART
 */

function pterosync_MetaData()
{
    return [
        "DisplayName" => "Ptero Sync",
        "APIVersion" => "1.1",
        "RequiresServer" => true,
    ];
}

function pterosync_loadLocations($params): array
{
    $data = pteroSyncApplicationApi($params, 'locations');
    $list = [];
    if ($data['status_code'] == 200) {
        $locations = $data['data'];
        foreach ($locations as $location) {
            $attr = $location['attributes'];
            $list[$attr['id']] = ucfirst($attr['short']);
        }
    }
    return $list;
}

function pterosync_loadEggs($params)
{
    $eggs = [];
    if (isset($_SESSION['nests'])) {
        $nests = $_SESSION['nests'];
        foreach ($nests as $nest) {
            $attr = $nest['attributes'];
            $nestId = $attr['id'];
            foreach ($attr['relationships']['eggs']['data'] as $egg) {
                $attr = $egg['attributes'];
                $eggs[$attr['id']] = $attr['name'] . ' (' . $nestId . ')';
            }
        }
    }
    return $eggs;
}

function pterosync_loadNests($params)
{
    $data = pteroSyncApplicationApi($params, 'nests?include=eggs');
    $list = [];
    if ($data['status_code'] == 200) {
        $nests = $data['data'];
        foreach ($nests as $nest) {
            $attr = $nest['attributes'];
            $nestId = $attr['id'];
            $list[$nestId] = $attr['name'];
        }
        $_SESSION['nests'] = $nests;
    }

    return $list;
}

function pterosyncAddHelpTooltip($message, $link)
{

    if ($link == "ports-ranges") {
        $link = 'https://github.com/wohahobg/pterosync/wiki/Ports-Ranges';
    } elseif ($link == "default-variables") {
        $link = 'https://github.com/wohahobg/pterosync/wiki/Default-Variables';
    } else {
        $link = 'https://github.com/wohahobg/pterosync/wiki/General-Information#' . $link;
    }
    // Use htmlspecialchars to encode special characters
    $encodedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    return sprintf('<a href="%s" target="_blank" data-toggle="tooltip" data-html="true" title="%s">Help</a>', $link, $encodedMessage);
}

function pterosync_ConfigKeys()
{
    return [
        "cpu",
        "disk",
        "memory",
        "swap",
        "location_id",
        "dedicated_ip",
        "nest_id",
        "io",
        "egg_id",
        "startup",
        "image",
        "databases",
        "server_name",
        "oom_disabled",
        "backups",
        "allocations",
        "ports_ranges",
        "default_variables",
        "server_port_offset",
        "feature_limits",
        "hide_server_status",
        "threads",
        "allow_server_configuration_edit",
        "dedicated_port",
    ];
}

function pterosync_ConfigOptions()
{
    $diskConfig = PteroSyncInstance::get()->disk_as_gb;
    $diskTitle = $diskConfig ? "Disk Space (GB)" : "Disk Space (MB)";
    $diskDescription = "Enter the amount of disk space to assign to the server. The value will be interpreted as " . ($diskConfig ? "gigabytes (GB)." : "megabytes (MB).");
    $diskDefault = $diskConfig ? 10 : 10240; // 10 GB or 10240 MB

    $memoryConfig = PteroSyncInstance::get()->memory_as_gb;
    $memoryTitle = $memoryConfig ? "Memory (GB)" : "Memory (MB)";
    $memoryDescription = "Enter the amount of memory to assign to the server. The value will be interpreted as " . ($memoryConfig ? "gigabytes (GB)." : "megabytes (MB).");
    $memoryDefault = $memoryConfig ? 1 : 1024; // 1 GB or 1024 MB

    $swapConfig = PteroSyncInstance::get()->swap_as_gb;
    $swapTitle = $swapConfig ? "Swap (GB)" : "Swap (MB)";
    $swapDescription = "Enter the amount of swap space to assign to the server. The value will be interpreted as " . ($swapConfig ? "gigabytes (GB)." : "megabytes (MB).");
    $swapDefault = $swapConfig ? 0.5 : 512; // 1 GB or 1024 MB

    $portDescription = "Specify port ranges for various server functions. The system will automatically search for available ports within these ranges under the same IP address. Ensure the ranges do not overlap. Note: 'SERVER_PORT' is required. Format: {\"SERVER_PORT\": \"start-end\", \"QUERY_PORT\": \"start-end\", \"RCON_PORT\": \"start-end\"}. Example: {\"SERVER_PORT\": \"7777-7780\", \"QUERY_PORT\": \"27015-27020\", \"RCON_PORT\": \"27020-27030\"}.";

    return [
        "cpu" => [
            "FriendlyName" => "<style></style> CPU Limit (%)",
            "Description" => pterosyncAddHelpTooltip('Amount of CPU to assign to the created server.', 'cpu-limit-'),
            "Type" => "text",
            "Size" => 25,
            "Default" => 100,
            'SimpleMode' => true,
        ],
        "disk" => [
            "FriendlyName" => $diskTitle,
            "Description" => pterosyncAddHelpTooltip($diskDescription, 'disk-space'),
            "Type" => "text",
            "Size" => 25,
            "Default" => $diskDefault,
            'SimpleMode' => true,
        ],
        "memory" => [
            "FriendlyName" => $memoryTitle,
            "Description" => pterosyncAddHelpTooltip($memoryDescription, 'memory'),
            "Type" => "text",
            "Size" => 25,
            "Default" => $memoryDefault,
            'SimpleMode' => true,
        ],
        "swap" => [
            "FriendlyName" => $swapTitle,
            "Description" => pterosyncAddHelpTooltip($swapDescription, 'swap'),
            "Type" => "text",
            "Default" => $swapDefault,
            "Size" => 25,
            'SimpleMode' => true,
        ],
        "location_id" => [
            "FriendlyName" => "Location ID",
            "Description" => pterosyncAddHelpTooltip("Select the location where the server will be deployed. Each location ID corresponds to a specific geographical data center.", 'location-id'),
            "Type" => "text",
            "Size" => 25,
            'SimpleMode' => true,
            'Loader' => 'pterosync_loadLocations',
        ],
        "dedicated_ip" => [
            "FriendlyName" => "Dedicated IP",
            "Description" => pterosyncAddHelpTooltip("Assign dedicated ip to the server (optional)", 'dedicated-ip'),
            "Type" => "yesno",
            "Size" => 25,
            'SimpleMode' => true,
        ],
        "nest_id" => [
            "FriendlyName" => "<span id='cNestId'></span> Nest ID",
            "Description" => pterosyncAddHelpTooltip("Choose a Nest ID that categorizes the type of server you wish to deploy. Nests are used to group similar servers.", 'nest-id'),
            "Type" => "text",
            "Size" => 25,
            'SimpleMode' => true,
            'Loader' => 'pterosync_loadNests',
        ],
        "io" => [
            "FriendlyName" => "Block IO Weight",
            "Description" => pterosyncAddHelpTooltip("Block IO Adjustment number (10-1000)", 'block-io-weight'),
            "Type" => "text",
            "Size" => 25,
            "Default" => "500",
            'SimpleMode' => true,
        ],
        "egg_id" => [
            "FriendlyName" => "<span id='cEggId'></span> Egg ID",
            "Description" => pterosyncAddHelpTooltip("Select the Egg ID to specify the software environment and settings for your server. Eggs define the application running on the server.", 'egg-id'),
            "Type" => "text",
            "Size" => 10,
            'SimpleMode' => true,
            'Loader' => 'pterosync_loadEggs',
        ],
        "startup" => [
            "FriendlyName" => "Startup",
            "Description" => pterosyncAddHelpTooltip("Custom startup command to assign to the created server (optional)", 'startup-command'),
            "Type" => "text",
            "Size" => 25,
            'SimpleMode' => true,
        ],
        "image" => [
            "FriendlyName" => "Image",
            "Description" => pterosyncAddHelpTooltip("Custom Docker image to assign to the created server (optional)", 'custom-docker-image'),
            "Type" => "text",
            "Size" => 25,
            'SimpleMode' => true,
        ],
        "databases" => [
            "FriendlyName" => "Databases",
            "Description" => pterosyncAddHelpTooltip("Client will be able to create this amount of databases for their server (optional)", 'databases'),
            "Type" => "text",
            "Size" => 25,
            "Default" => 1,
            'SimpleMode' => true,
        ],
        "server_name" => [
            "FriendlyName" => "Server Name",
            "Description" => pterosyncAddHelpTooltip("The name of the server as shown on the panel (optional)", 'server-name'),
            "Type" => "text",
            "Size" => 25,
            "Default" => 'Ptero Sync Server',
            'SimpleMode' => true,
        ],
        "oom_disabled" => [
            "FriendlyName" => "Disable OOM Killer",
            "Description" => pterosyncAddHelpTooltip("Should the Out Of Memory Killer be disabled (optional)", 'disable-oom-killer'),
            "Type" => "yesno",
            "Size" => 25,
            'SimpleMode' => true,
        ],
        "backups" => [
            "FriendlyName" => "Backups",
            "Description" => pterosyncAddHelpTooltip("Client will be able to create this amount of backups for their server (optional)", 'backups'),
            "Type" => "text",
            "Size" => 25,
            'SimpleMode' => true,
        ],
        "allocations" => [
            "FriendlyName" => "Allocations",
            "Description" => pterosyncAddHelpTooltip("Client will be able to create this amount of allocations for their server (optional)", 'allocations'),
            "Type" => "text",
            "Size" => 25,
            'SimpleMode' => true,
        ],
        "ports_ranges" => [
            "FriendlyName" => "Ports Ranges",
            "Description" => pterosyncAddHelpTooltip($portDescription, 'ports-ranges'),
            "Type" => "textarea",
            "Size" => 10,
            "default" => '{"SERVER_PORT": "25565-25669"}',
            'SimpleMode' => true,
        ],
        "default_variables" => [
            "FriendlyName" => "Default Variables",
            "Description" => pterosyncAddHelpTooltip("Define default values for server variables in JSON format. For instance, set MAX_PLAYERS to 30 with {\"MAX_PLAYERS\": 30}. This is useful for consistent server settings and quick configuration.", 'default-variables'),
            "Type" => "textarea",
            "default" => '{"MAX_PLAYERS": "30"}',
            "Size" => 25,
            'SimpleMode' => true,
        ],
        'server_port_offset' => [
            'FriendlyName' => "Server Port Offset",
            "Description" => pterosyncAddHelpTooltip("Specify an offset for the Server Port, used for games requiring a specific increment above the SERVER_PORT. Enter '1' for games like ARK: Survival Evolved that need SERVER_PORT +1, or '123' for games like MTA requiring a larger increment. To disable this feature, simply input '0'", 'server-port-offset'),
            "Type" => "text",
            "default" => 0,
            "Size" => 25,
            'SimpleMode' => true,
        ],
        "feature_limits" => [
            'FriendlyName' => "Feature Limits",
            "Description" => pterosyncAddHelpTooltip("Feature limits are ideal for overriding add-ons that are integrated into your Pterodactyl panel. Ensure that the input is valid JSON. For more information, please refer to our Wiki page.", 'feature-limits'),
            "Type" => "text",
            "default" => '0',
            "Size" => 25,
            'SimpleMode' => true,
        ],
        //We must keep this as this name
        //since a lot of people already have set `off` or so.
        //and if we change the key name we are going to fuck up them.
        "hide_server_status" => [
            'FriendlyName' => "Server Status Type",
            "Description" => pterosyncAddHelpTooltip("Select the name to be used for Server Status. Ensure the name/egg is correctly spelled in English, such as Minecraft or Source.", 'game-server-status'),
            "Type" => "dropdown",
            "Default" => "Nest",
            "Options" => [
                'nest' => 'Nest Name',
                'egg' => 'Egg Name',
                'off' => 'Do not show server status',
            ],
            "Size" => 25,
            'SimpleMode' => true,
        ],
        //        'threads' => "Enter the specific CPU cores that this process can run on, or leave blank to allow all cores. This can be a single number, or a comma seperated list. Example: 0, 0-1,3, or 0,1,3,4."
        "threads" => [
            'FriendlyName' => "CPU Pinning",
            "Description" => pterosyncAddHelpTooltip("Enter the specific CPU cores that this process can run on, or leave blank to allow all cores. This can be a single number, or a comma seperated list. Example: 0, 0-1,3, or 0,1,3,4.", 'cpu-pinning'),
            "Type" => "text",
            "Size" => 25,
            "default" => null,
            'SimpleMode' => true,
        ],
        "allow_server_configuration_edit" => [
            'FriendlyName' => "Server Configuration Access",
            "Description" => pterosyncAddHelpTooltip("Grant users the ability to edit their server's startup parameters and node configuration settings via the Pterodactyl API. This setting controls the level of access users have to critical server configurations.", 'server-configuration-access'),
            "Type" => "dropdown",
            "Default" => "disabled",
            "Options" => [
                'disabled' => 'Do not allow',
                'both' => 'Allow both startup and settings modifications',
                'startup' => 'Allow only startup modifications',
                'settings' => 'Allow only settings modifications',
            ],
            "Size" => 25,
            'SimpleMode' => true,
        ],
        "dedicated_port" => [
            "FriendlyName" => "Dedicated Port",
            "Description" => pterosyncAddHelpTooltip("Works like Dedicated IP but reserves only a specific port instead of the entire IP. It ensures that the first port from the configured Ports Range (e.g., SERVER_PORT = 27015-27020, which means 27015 will always be used) is assigned to the server, guaranteeing a fixed port for that client.", 'dedicated-port'),
            "Type" => "yesno",
            "Size" => 25,
            'SimpleMode' => true,
        ]

    ];
}

function pteroSyncGetOption(array $params, $id, $default = NULL)
{
    $options = pterosync_ConfigKeys();

    $friendlyName = $options[$id];
    if (isset($params['A'][$friendlyName]) && $params['configoptions'][$friendlyName] !== '') {
        return $params['configoptions'][$friendlyName];
    } else if (isset($params['configoptions'][$id]) && $params['configoptions'][$id] !== '') {
        return $params['configoptions'][$id];
    } else if (isset($params['customfields'][$friendlyName]) && $params['customfields'][$friendlyName] !== '') {
        return $params['customfields'][$friendlyName];
    } else if (isset($params['customfields'][$id]) && $params['customfields'][$id] !== '') {
        return $params['customfields'][$id];
    }

    $found = false;
    $i = 0;
    foreach ($options as $key) {
        $i++;
        if ($key === $id) {
            $found = true;
            break;
        }
    }
    if ($found && isset($params['configoption' . $i]) && $params['configoption' . $i] !== '') {
        return $params['configoption' . $i];
    }
    return $default;
}

function pterosync_TestConnection(array $params)
{
    $solutions = [
        0 => "Check module debug log for more detailed error.",
        401 => "Authorization header either missing or not provided.",
        403 => "Double check the password (which should be the Application Key).",
        404 => "Result not found.",
        422 => "Validation error.",
        500 => "Panel errored, check panel logs.",
    ];

    $err = "";
    try {
        $response = pteroSyncApplicationApi($params, 'nodes');

        if ($response['status_code'] !== 200) {
            $status_code = $response['status_code'];
            $err = "Invalid status_code received: " . $status_code . ". Possible solutions: " . $solutions[$status_code] ?? "None.";
        } else {
            if ($response['meta']['pagination']['count'] === 0) {
                $err = "Authentication successful, but no nodes are available.";
            }
        }
    } catch (Exception $e) {
        logModuleCall("PteroSync-WHMCS", 'TEST CONNECTION', $params, $e->getMessage(), $e->getTraceAsString());
        $err = $e->getMessage();
    }

    return [
        "success" => $err === "",
        "error" => $err,
    ];
}


function pterosync_CreateAccount(array $params)
{

    try {
        PteroSyncInstance::get()->service_id = $params['serviceid'];
        $portsJson = pteroSyncGetOption($params, 'ports_ranges');
        $portsJson = trim($portsJson);
        $portsArray = [];
        if ($portsJson !== '') {
            $pattern = '/^(\d+-\d+)(,\d+-\d+)*$/';
            if (!preg_match_all($pattern, $portsJson, $matches)) {
                $portsArray = json_decode($portsJson, true);
                if (!is_array($portsArray)) {
                    throw new Exception('Failed to create server because ports is not in valid JSON format.');
                }
            }
        }

        // Pre-check: Ensure all required ports are available before creating the server
        $nestId = pteroSyncGetOption($params, 'nest_id');
        $eggId = pteroSyncGetOption($params, 'egg_id');
        $eggData = pteroSyncApplicationApi($params, 'nests/' . $nestId . '/eggs/' . $eggId . '?include=variables');
        if ($eggData['status_code'] !== 200) throw new Exception('Failed to get egg data, received error code: ' . $eggData['status_code'] . '. Enable module debug log for more info.');
        if ($portsArray) {
            pteroSyncProcessAllocations($eggData, $portsArray);
        }
        $location_id = pteroSyncGetOption($params, 'location_id');
        $nodes = pteroSyncApplicationApi($params, 'nodes?include=allocations');
        $targetNode = null;
        $allocations = [];
        foreach ($nodes['data'] as $nodeData) {
            if ($nodeData['attributes']['location_id'] == $location_id) {
                $targetNode = $nodeData['attributes'];
                $allocations = $nodeData['attributes']['relationships']['allocations']['data'];
                break;
            }
        }
        if (!$targetNode) throw new Exception('No node found for the selected location.');
        PteroSyncInstance::get()->node_allocations = $allocations;
        $ips = pteroSyncMakeIParray();
        $foundPorts = pteroSyncfindPorts($portsArray, $ips);
        if (!$foundPorts || count($foundPorts) < count(PteroSyncInstance::get()->variables)) {
            throw new Exception('Not all required ports are available for this server. Please adjust your port ranges or try again later.');
        }

        $serverId = pteroSyncGetServer($params);
        if ($serverId) throw new Exception('Failed to create server because it is already created.');
        $customFieldId = pteroSyncGetCustomFieldId($params);

        $userResult = PteroSyncInstance::get()->getPterodactylUser($params, [
            'username' => pteroSyncGetOption($params, 'username', pteroSyncGenerateUsername()),
            'id' => $params['clientsdetails']['client_id'],
            'email' => $params['clientsdetails']['email'],
            'firstname' => $params['clientsdetails']['firstname'],
            'lastname' => $params['clientsdetails']['lastname'],
        ]);

        if ($userResult['status_code'] === 200 || $userResult['status_code'] === 201) {
            if (!isset($userResult['attributes']['id'])) {
                throw new Exception("Failed to get the client pterodactyl's account.Enable module debug log for more info.");
            }
            $userId = $userResult['attributes']['id'];
        } else {
            throw new Exception('Failed to create user, received error code: ' . $userResult['status_code'] . '. Enable module debug log for more info.');
        }

        $eggData = pteroSyncApplicationApi($params, 'nests/' . $nestId . '/eggs/' . $eggId . '?include=variables');
        if ($eggData['status_code'] !== 200) throw new Exception('Failed to get egg data, received error code: ' . $eggData['status_code'] . '. Enable module debug log for more info.');

        $environment = [];
        $default_variables = pteroSyncGetOption($params, 'default_variables');
        $default_variables = json_decode($default_variables, true);

        foreach ($eggData['attributes']['relationships']['variables']['data'] as $key => $val) {
            $attr = $val['attributes'];
            $var = $attr['env_variable'];
            $default = $attr['default_value'];
            $friendlyName = pteroSyncGetOption($params, $attr['name']);
            $envName = pteroSyncGetOption($params, $attr['env_variable']);

            if (isset($friendlyName)) {
                $environment[$var] = "" . $friendlyName . "";
            } elseif (isset($envName)) {
                $environment[$var] = "" . $envName . "";
            } elseif (isset($default_variables[$var]) && !in_array($default_variables[$var], PteroSyncInstance::get()->dynamic_variables)) {
                $environment[$var] = "" . $default_variables[$var] . "";
            } else {
                $environment[$var] = "" . $default . "";
            }
        }

        if ($default_variables) {
            foreach ($default_variables as $default_variable => $default_variable_value) {
                if (in_array($default_variable_value, PteroSyncInstance::get()->dynamic_variables)) {
                    PteroSyncInstance::get()->dynamic_environment_array[$default_variable] = $default_variable_value;
                }
            }
        }

        $id = (string)$params['serviceid'];
        $name = pteroSyncGetOption($params, 'server_name', pteroSyncGenerateUsername() . '_' . $id);
        [$memory, $swap, $disk] = pteroSyncGetMemorySwapAndDisk($params);

        $io = pteroSyncGetOption($params, 'io');
        $cpu = pteroSyncGetOption($params, 'cpu');

        $dedicated_ip = (bool)pteroSyncGetOption($params, 'dedicated_ip');
        $dedicated_port = (bool)pteroSyncGetOption($params, 'dedicated_port');
        PteroSyncInstance::get()->dedicated_ip = $dedicated_ip;

        if ($dedicated_port) {
            if (isset($portsArray['SERVER_PORT'])) {
                $portsData = $portsArray;
                $explode = explode('-', $portsArray['SERVER_PORT']);
                $portsData['SERVER_PORT'] = $explode[0] ?? null;
                $portsArray = $portsData;
            }
        }

        //PteroSyncInstance::get()->server_port_offset = pteroSyncGetOption($params, 'server_port_offset');

        if ($portsArray) {
            $port_range = isset($portsArray['SERVER_PORT']) ? explode(',', $portsArray['SERVER_PORT']) : [];
        } else {
            $port_range = !empty($portsJson) ? explode(',', $portsJson) : [];
        }

        $image = pteroSyncGetOption($params, 'image', $eggData['attributes']['docker_image']);
        $startup = pteroSyncGetOption($params, 'startup', $eggData['attributes']['startup']);
        $databases = pteroSyncGetOption($params, 'databases');
        $maximumAllocations = pteroSyncGetOption($params, 'allocations');
        $backups = pteroSyncGetOption($params, 'backups');
        $oom_disabled = (bool)pteroSyncGetOption($params, 'oom_disabled');

        $threads = pterosync_validateThreads(pteroSyncGetOption($params, 'threads'));

        $serverData = [
            'name' => $name,
            'user' => (int)$userId,
            'nest' => (int)$nestId,
            'egg' => (int)$eggId,
            'docker_image' => $image,
            'startup' => $startup,
            'oom_disabled' => $oom_disabled,
            'limits' => [
                'memory' => (int)$memory,
                'swap' => (int)$swap,
                'io' => (int)$io,
                'cpu' => (int)$cpu,
                'disk' => (int)$disk,
                'threads' => (string)$threads,
            ],
            'feature_limits' => [
                'databases' => $databases ? (int)$databases : null,
                'allocations' => (int)$maximumAllocations,
                'backups' => (int)$backups,
            ],
            'deploy' => [
                'locations' => [(int)$location_id],
                'dedicated_ip' => $dedicated_ip,
                'port_range' => $port_range,
            ],
            'environment' => $environment,
            'start_on_completion' => true,
            'external_id' => (string)$params['serviceid'],
        ];

        $feature_limits = pteroSyncGetOption($params, 'feature_limits');
        $feature_limits = json_decode($feature_limits, true);
        if ($feature_limits) {
            foreach ($feature_limits as $featureName => $default) {
                $value = pteroSyncGetOption($params, $featureName, $default);
                $feature_limits[$featureName] = $value;
            }
            $serverData['feature_limits'] = array_merge($serverData['feature_limits'], $feature_limits);
        }

        $server = pteroSyncApplicationApi($params, 'servers?include=allocations', $serverData, 'POST');

        if ($server['status_code'] === 400) throw new Exception('Couldn\'t find any nodes satisfying the request.');
        if ($server['status_code'] !== 201) throw new Exception('Failed to create the server, received the error code: ' . $server['status_code'] . '. Enable module debug log for more info.');

        $serverId = $server['attributes']['id'];
        $_SERVER_ID = $server['attributes']['uuid'];


        $serverAllocations = $server['attributes']['relationships']['allocations']['data'];
        $allocation = $server['attributes']['allocation'];
        pteroSync_getServerIPAndPort($serverAllocations, $allocation);

        $serverNode = $server['attributes']['node'];
        $foundPorts = [];

        if ($portsArray) {
            pteroSyncGetNodeAllocations($params, $serverNode);
            pteroSyncProcessAllocations($eggData, $portsArray);
        }

        if (!PteroSyncInstance::get()->variables) {
            pteroSyncLog('VARIABLES', 'No variables founds.', $portsArray);
        }

        if (!PteroSyncInstance::get()->node_allocations) {
            pteroSyncLog('NODE ALLOCATIONS', 'Node allocations not found.', [$serverNode]);
        }

        if (PteroSyncInstance::get()->variables && PteroSyncInstance::get()->node_allocations) {
            $ips = pteroSyncMakeIParray();
            $foundPorts = pteroSyncfindPorts($portsArray, $ips);
        }

        if (!$foundPorts && PteroSyncInstance::get()->variables && PteroSyncInstance::get()->node_allocations) {
            pteroSyncLog('Ports not founds', 'Ports not founds.', [
                'results' => PteroSyncInstance::get()->fetchedResults,
                'variables' => PteroSyncInstance::get()->variables
            ]);
        }

        if ($foundPorts) {
            pteroSyncLog('Found ports', 'Found ports.', $foundPorts);
            $_SERVER_PORT_ID = $serverAllocations[0]['attributes']['id'];

            $allocationArray = [];
            $environment = [];
            $additional = [];
            foreach ($foundPorts as $key => $var) {
                $environment[$key] = "" . $var['port'] . "";
                if ($key !== 'SERVER_PORT') {
                    $additional[] = $var['id'];
                    $maximumAllocations++;
                }
            }

            // Always set the main allocation to SERVER_PORT
            $allocationArray['allocation'] = $foundPorts['SERVER_PORT']['id'];
            $allocationArray['add_allocations'] = $additional;

            if (PteroSyncInstance::get()->getDynamicEnvironmentArray()) {
                PteroSyncInstance::get()->addFileLog(PteroSyncInstance::get()->getDynamicEnvironmentArray(), 'Setting Dynamic Environment');
                foreach (PteroSyncInstance::get()->getDynamicEnvironmentArray() as $environmentName => $variableName) {
                    if (isset($environment[$variableName])) {
                        $environment[$environmentName] = $environment[$variableName];
                    }
                }
            }
            if (isset($environment['SERVER_PORT'])) {
                unset($environment['SERVER_PORT']);
            }

            $newServerArray = array_merge([
                'memory' => (int)$memory,
                'swap' => (int)$swap,
                'io' => (int)$io,
                'cpu' => (int)$cpu,
                'disk' => (int)$disk,
                'oom_disabled' => $oom_disabled,
                'feature_limits' => [
                    'databases' => (int)$databases,
                    'allocations' => (int)$maximumAllocations,
                    'backups' => (int)$backups,
                ],
            ], $allocationArray);

            if ($feature_limits) {
                $newServerArray['feature_limits'] = array_merge($serverData['feature_limits'], $feature_limits);
            }
            $updateResult = pteroSyncApplicationApi($params, 'servers/' . $serverId . '/build?include=allocations', $newServerArray, 'PATCH');

            if ($updateResult['status_code'] !== 200) throw new Exception('Failed to update build of the server, received error code: ' . $updateResult['status_code'] . '. Enable module debug log for more info.');


            $allocation = $updateResult['attributes']['allocation'];
            $serverAllocations = $updateResult['attributes']['relationships']['allocations']['data'];
            pteroSync_getServerIPAndPort($serverAllocations, $allocation);
            pteroSyncApplicationApi($params, 'servers/' . $serverId . '/startup', [
                'startup' => $server['attributes']['container']['environment']['STARTUP'],
                'egg' => $server['attributes']['egg'],
                'image' => $server['attributes']['container']['image'],
                'environment' => array_merge($serverData['environment'], $environment),
                'skip_scripts' => false,
            ], 'PATCH');

        }

        unset($params['password']);
        pteroSync_updateServerDomain($params);
        pteroSyncUpdateCustomField($params, $customFieldId, $_SERVER_ID);
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'username' => '',
            'password' => '',
        ]);

    } catch (Exception $err) {
        return $err->getMessage();
    }
    return 'success';
}

function pterosync_SuspendAccount(array $params)
{
    try {
        $serverId = pteroSyncGetServer($params);
        if (!$serverId) throw new Exception('Failed to suspend server because it doesn\'t exist.');

        $suspendResult = pteroSyncApplicationApi($params, 'servers/' . $serverId . '/suspend', [], 'POST');
        if ($suspendResult['status_code'] !== 204) throw new Exception('Failed to suspend the server, received error code: ' . $suspendResult['status_code'] . '. Enable module debug log for more info.');
    } catch (Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pterosync_UnsuspendAccount(array $params)
{
    try {
        $serverId = pteroSyncGetServer($params);
        if (!$serverId) throw new Exception('Failed to unsuspend server because it doesn\'t exist.');

        $suspendResult = pteroSyncApplicationApi($params, 'servers/' . $serverId . '/unsuspend', [], 'POST');
        if ($suspendResult['status_code'] !== 204) throw new Exception('Failed to unsuspend the server, received error code: ' . $suspendResult['status_code'] . '. Enable module debug log for more info.');
    } catch (Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pterosync_TerminateAccount(array $params)
{
    try {
        $serverId = pteroSyncGetServer($params);
        if (!$serverId) throw new Exception('Failed to terminate server because it doesn\'t exist.');

        $deleteResult = pteroSyncApplicationApi($params, 'servers/' . $serverId, [], 'DELETE');
        if ($deleteResult['status_code'] !== 204) throw new Exception('Failed to terminate the server, received error code: ' . $deleteResult['status_code'] . '. Enable module debug log for more info.');
    } catch (Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pterosync_ChangePassword(array $params)
{

    try {
        if (PteroSyncInstance::get()->enable_client_area_password_changer !== true) {
            throw new Exception ("Password Change Unavailable: The option to change passwords directly from the product page is currently disabled. For password updates, please proceed to the 'Change Password' tab.");
        }
        if ($params['password'] === '') throw new Exception('The password cannot be empty.');

        $serverData = pteroSyncGetServer($params, true);
        if (!$serverData) throw new Exception('Failed to change password because linked server doesn\'t exist.');

        $userId = $serverData['user'];
        $userResult = pteroSyncApplicationApi($params, 'users/' . $userId);
        if ($userResult['status_code'] !== 200) throw new Exception('Failed to retrieve user, received error code: ' . $userResult['status_code'] . '.');

        $updateResult = pteroSyncApplicationApi($params, 'users/' . $serverData['user'], [
            'username' => $userResult['attributes']['username'],
            'email' => $userResult['attributes']['email'],
            'first_name' => $userResult['attributes']['first_name'],
            'last_name' => $userResult['attributes']['last_name'],

            'password' => $params['password'],
        ], 'PATCH');
        if ($updateResult['status_code'] !== 200) throw new Exception('Failed to change password, received error code: ' . $updateResult['status_code'] . '.');

        unset($params['password']);
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'username' => '',
            'password' => '',
        ]);
    } catch (Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pterosync_ChangePackage(array $params)
{

    try {

        $serverData = pteroSyncGetServer($params, true);
        if (!$serverData) throw new Exception('Failed to change package of server because it doesn\'t exist.');
        $serverId = $serverData['id'];

        [$memory, $swap, $disk] = pteroSyncGetMemorySwapAndDisk($params);

        $io = pteroSyncGetOption($params, 'io');
        $cpu = pteroSyncGetOption($params, 'cpu');
        $databases = pteroSyncGetOption($params, 'databases');
        $allocations = pteroSyncGetOption($params, 'allocations');
        $backups = pteroSyncGetOption($params, 'backups');
        $oom_disabled = (bool)pteroSyncGetOption($params, 'oom_disabled');

        $threads = pterosync_validateThreads(pteroSyncGetOption($params, 'threads'));
        $updateData = [
            'allocation' => $serverData['allocation'],
            'memory' => (int)$memory,
            'swap' => (int)$swap,
            'io' => (int)$io,
            'cpu' => (int)$cpu,
            'disk' => (int)$disk,
            'threads' => (string)$threads,
            'oom_disabled' => $oom_disabled,
            'feature_limits' => [
                'databases' => (int)$databases,
                'allocations' => (int)$allocations,
                'backups' => (int)$backups,
            ],
        ];

        $feature_limits = pteroSyncGetOption($params, 'feature_limits');
        $feature_limits = json_decode($feature_limits, true);
        if ($feature_limits) {
            $updateData['feature_limits'] = array_merge($updateData['feature_limits'], $feature_limits);
        }

        $updateResult = pteroSyncApplicationApi($params, 'servers/' . $serverId . '/build?include=allocations', $updateData, 'PATCH');
        if ($updateResult['status_code'] !== 200) throw new Exception('Failed to update build of the server, received error code: ' . $updateResult['status_code'] . '. Enable module debug log for more info.');
        $allocation = $updateResult['attributes']['allocation'];
        $serverAllocations = $updateResult['attributes']['relationships']['allocations']['data'];

        pteroSync_getServerIPAndPort($serverAllocations, $allocation);

        $nestId = pteroSyncGetOption($params, 'nest_id');
        $eggId = pteroSyncGetOption($params, 'egg_id');
        $eggData = pteroSyncApplicationApi($params, 'nests/' . $nestId . '/eggs/' . $eggId . '?include=variables');
        if ($eggData['status_code'] !== 200) throw new Exception('Failed to get egg data, received error code: ' . $eggData['status_code'] . '. Enable module debug log for more info.');

        $default_variables = pteroSyncGetOption($params, 'default_variables');
        $default_variables = json_decode($default_variables, true);
        $environment = [];
        foreach ($eggData['attributes']['relationships']['variables']['data'] as $key => $val) {
            $attr = $val['attributes'];
            $var = $attr['env_variable'];
            $default = $attr['default_value'];
            $friendlyName = pteroSyncGetOption($params, $attr['name']);
            $envName = pteroSyncGetOption($params, $attr['env_variable']);

            if (isset($friendlyName)) {
                $environment[$var] = "" . $friendlyName . "";
            } elseif (isset($envName)) {
                $environment[$var] = "" . $envName . "";
            } elseif (isset($default_variables[$var]) && !in_array($default_variables[$var], PteroSyncInstance::get()->dynamic_variables)) {
                $environment[$var] = "" . $default_variables[$var] . "";
            } else {
                $environment[$var] = "" . $default . "";
            }
        }


        $image = pteroSyncGetOption($params, 'image', $eggData['attributes']['docker_image']);
        $startup = pteroSyncGetOption($params, 'startup', $eggData['attributes']['startup']);
        $updateData = [
            'environment' => $environment,
            'startup' => $startup,
            'egg' => (int)$eggId,
            'image' => $image,
            'skip_scripts' => false,
        ];
        $updateResult = pteroSyncApplicationApi($params, 'servers/' . $serverId . '/startup', $updateData, 'PATCH');
        if ($updateResult['status_code'] !== 200) throw new Exception('Failed to update startup of the server, received error code: ' . $updateResult['status_code'] . '. Enable module debug log for more info.');

        if ($eggId !== $serverData['egg']) {
            //TODO Option to re install the egg
            //TODO what if the egg id is not same ?
            //TOOD should we looking for new ports ?
            //TOOD what should we do ?
            //pteroSyncApplicationApi($params, 'servers/' . $serverId . '/reinstall', [], 'POST');
        }

        $_SERVER_ID = $serverData['uuid'];
        $customFieldId = pteroSyncGetCustomFieldId($params);

        pteroSync_updateServerDomain($params);
        pteroSyncUpdateCustomField($params, $customFieldId, $_SERVER_ID);
    } catch (Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pterosync_AdminCustomButtonArray()
{
//    return array(
//        "Button 1 Display Value" => "buttonOneFunction",
//        "Button 2 Display Value" => "buttonTwoFunction",
//    );
}

function pterosync_LoginLink(array $params)
{
    if ($params['moduletype'] !== 'pterosync') return;
    try {
        $server = pteroSyncGetServer($params, true);
        if (!$server) return;

        $hostname = pteroSyncGetHostname($params);
        $button1 = '<a class="btn btn-info text-uppercase"  
                    href="' . $hostname . '/server/' . $server['identifier'] . '" target="_blank">
                 <i class="fas fa-eye fa-fw"></i>
                View Server
              </a>';
        $button2 = '<a class="btn btn-primary text-uppercase"  
                 href="' . $hostname . '/admin/servers/view/' . $server['id'] . '" 
                 target="_blank">
                 <i class="fas fa-sign-in fa-fw"></i>
                Admin View
                </a>';
        // JavaScript to add buttons and hide the existing button
        echo '<script>
            const link1 = `' . $button1 . '`;
            const link2 = `' . $button2 . '`;
          </script>';

        echo '<script>
            jQuery(document).ready(function($) {
                // Hide the existing button
                $("#btnLoginLinkTrigger").hide();
               
                var buttonGroup = $("#btnLoginLinkTrigger").parent(); // Assuming buttons are in the same group

                // Add new buttons
                $(buttonGroup).prepend(link1);
                $(buttonGroup).prepend(link2);

            });
          </script>';
        return $button2;
    } catch (Exception $err) {

    }
}

function pterosync_AdminServicesTabFields($params)
{

}


function pterosync_ClientArea(array $params)
{
    if ($params['moduletype'] !== 'pterosync') return;

    global $_LANG;

    try {
        $isAdmin = $_SESSION['adminid'] ?? 0;
        $hostname = pteroSyncGetHostname($params);
        $serverId = $params['customfields']['UUID (Server ID)'];
        $serverStatusType = pteroSyncGetOption($params, 'hide_server_status');
        $allowServerConfigurationEdit = pteroSyncGetOption($params, 'allow_server_configuration_edit');
        $allowStartUpEdit = false;
        $allowSettingsEdit = false;

        if ($allowServerConfigurationEdit == 'both') {
            $allowStartUpEdit = true;
            $allowSettingsEdit = true;
        }

        if ($allowServerConfigurationEdit == 'startup' && PteroSyncInstance::get()->allow_startup_edit === true) {
            $allowStartUpEdit = true;
        }
        if ($allowServerConfigurationEdit == 'settings' && PteroSyncInstance::get()->allow_variables_edit === true) {
            $allowSettingsEdit = true;
        }
        //hide this for now ?
        $allowStartUpEdit = false;
        $params['allowSettingsEdit'] = $allowSettingsEdit;
        $params['allowStartUpEdit'] = $allowStartUpEdit;

        $serverData = pteroSyncGetServer($params, true, 'user,node,allocations,nest,egg');

        if (!$serverData) {
            return [
                'templatefile' => 'templates/error.tpl',
                'vars' => []
            ];
        }

        $endpoint = 'servers/' . $serverData['identifier'] . '/resources';
        $serverState = pteroSyncClientApi($params, $endpoint);
        if (isset($_GET['modop']) && $_GET['modop'] == 'custom' && isset($_GET['a'])) {


            if ($serverState['status_code'] === 404) {
                pteroSyncreturnJsonMessage($_LANG['SERVER_NOT_FOUND'], 404);
            }

            $action = match ($_GET['a']) {
                'startServer' => 'pteroSyncStartServer',
                'restartServer' => 'pteroSyncRestartServer',
                'stopServer' => 'pteroSyncStopServer',
                'killServer' => 'pteroSyncKillServer',
                'saveServerVariables' => 'pteroSyncSaveServerVariables',
                'saveServerStartup' => 'pteroSyncSaveServerStartup',
                'getState', 'getFtpDetails' => 'pteroSyncServerState',
                default => false,
            };

            if ($action !== false) {
                $action($params, $serverData, $serverState['attributes']['current_state']);
                exit(200);
            }

            pteroSyncreturnJsonMessage('ACTION_NOT_FOUND');
        }


        [$game, $address, $queryPort] = pteroSyncGenerateServerStatusArray($serverData, $serverStatusType);

        // Update server UUID if empty
        if ($serverId == '') {
            $serverId = $serverData['uuid'];
            $customFieldId = pteroSyncGetCustomFieldId($params);
            pteroSyncUpdateCustomField($params, $customFieldId, $serverData['uuid']);
        }

        // Update server IP if empty
        if ($params['domain'] == '') {
            pteroSync_updateServerDomain($params);
        }

        $serverIp = $params['domain'];
        if ($address !== false) {
            $parts = explode(':', $params['domain']);
            $serverIp = $address;
            if (isset($parts[1])) {
                $serverIp = $address . ':' . $parts[1];
            }
        }

        $actionUrl = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $vars = [
            'moduleDir' => __DIR__,
            'serviceId' => $params['serviceid'],
            'serverData' => $serverData,
            'currentState' => $serverState['attributes']['current_state'],
            'serverIp' => $serverIp,
            'serverId' => $serverId,
            'isAdmin' => $isAdmin,
            'allowStartUpEdit' => $allowStartUpEdit,
            'allowSettingsEdit' => $allowSettingsEdit
        ];


        $userAttributes = $serverData['relationships']['user']['attributes'];
        $nodeAttributes = $serverData['relationships']['node']['attributes'];
        $overviewVars = [
            'serviceUrl' => $hostname . '/server/' . $serverData['identifier'],
            'getStateUrl' => $actionUrl . '&modop=custom&a=getState',
            'startUrl' => $actionUrl . '&modop=custom&a=startServer',
            'rebootUrl' => $actionUrl . '&modop=custom&a=restartServer',
            'stopUrl' => $actionUrl . '&modop=custom&a=stopServer',
            'killUrl' => $actionUrl . '&modop=custom&a=killServer',
            'ftpDetails' => [
                'username' => $userAttributes['username'] . '.' . $serverData['identifier'],
                'host' => 'sftp://' . $nodeAttributes['fqdn'] . ':' . $nodeAttributes['daemon_sftp']
            ],
            'gameQueryData' => [
                'game' => $game,
                'address' => $address,
                'port' => $queryPort,
            ]
        ];

        $vars = array_merge($overviewVars, $vars);
        [$variables, $meta] = pteroSyncGetServerVariables($params, $serverData['uuid']);

        $environment = $serverData['container']['environment'];
        $editableVariables = [];
        if ($variables) {
            foreach ($variables as $variable) {
                $attr = $variable['attributes'];
                if ($attr['is_editable']) {

                    $pattern = '/(?<!<a href=")(https?:\/\/[^\s"]+)(?!")/i';
                    $replacement = '<a href="$1" target="_blank">$1</a>';
                    $attr['description'] = preg_replace($pattern, $replacement, $attr['description']);


                    $rules = $attr['rules'];
                    $arr = $attr;
                    $arr['options'] = [];
                    $arr['rule'] = 'input';
                    $arr['max_input'] = 255;

                    if (preg_match('/\bin:0,1\b/', $rules) && preg_match('/in:0,1/', $rules) && !preg_match('/in:0,1,/', $rules)) {
                        $arr['rule'] = 'switch';
                    }

                    if (str_contains($rules, 'numeric') && $arr['rule'] != 'switch') {
                        $arr['rule'] = 'number';
                    }

                    if (str_contains($rules, 'in:') && $arr['rule'] != 'switch') {
                        $arr['rule'] = 'select';
                        $explode = explode('in:', $rules);
                        if (isset($explode[1])) {
                            $arr['options'] = explode(',', $explode[1]);
                        }
                    }
                    if (str_contains($rules, 'max:')) {
                        $arr['max_input'] = str_replace('max:', '', $rules);
                    }


                    $arr['required'] = str_contains($rules, 'required');


                    $editableVariables[] = $arr;
                }
            }

        }
        $vars = array_merge([
            'editableVariables' => $editableVariables,
            'environment' => $environment,
            'saveSettingUrl' => $actionUrl . '&modop=custom&a=saveServerVariables',
            'saveStartupUrl' => $actionUrl . '&modop=custom&a=saveServerStartup',
        ], $vars);


        return [
            'templatefile' => 'clientarea.tpl',
            'vars' => $vars
        ];
    } catch (Exception $err) {
        echo $err->getMessage();
        die(400);
    }
}
