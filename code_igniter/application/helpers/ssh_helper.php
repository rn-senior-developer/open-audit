<?php
/**
#  Copyright 2003-2015 Opmantek Limited (www.opmantek.com)
#
#  ALL CODE MODIFICATIONS MUST BE SENT TO CODE@OPMANTEK.COM
#
#  This file is part of Open-AudIT.
#
#  Open-AudIT is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as published
#  by the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  Open-AudIT is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with Open-AudIT (most likely in a file named LICENSE).
#  If not, see <http://www.gnu.org/licenses/>
#
#  For further information on Open-AudIT or for a license other than AGPL please see
#  www.opmantek.com or email contact@opmantek.com
#
# *****************************************************************************
*
* PHP version 5.3.3
*
* @category  Helper
* @package   Discoveries
* @author    Mark Unwin <marku@opmantek.com>
* @copyright 2014 Opmantek
* @license   http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
* @version   GIT: Open-AudIT_4.3.2
* @link      http://www.open-audit.org
*/

/**
* Base Helper Discoveries
*
* @access   public
* @category Helper
* @package  Discoveries
* @author   Mark Unwin <marku@opmantek.com>
* @license  http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
* @link     http://www.open-audit.org
 */
if ( ! defined('BASEPATH')) {
     exit('No direct script access allowed');
}

if ( !  function_exists('scp')) {
    /**
     * Copy a file to the target
     * @param  object $parameters Should contain IP, credentials, source, destincation, log
     * @return array  Contains the output and status flag, or false on fail
     */
    function scp($parameters)
    {
        $item_start = microtime(true);
        $CI = & get_instance();
        $message = '';
        if (empty($parameters->ip)) {
            $message = 'No IP supplied to scp function.';
        }
        if ( ! filter_var($parameters->ip, FILTER_VALIDATE_IP)) {
            $message = 'Invalid IP supplied to scp function.';
        }
        if ( ! is_object($parameters->credentials)) {
            $message = 'No credentials supplied to scp function.';
        }
        if (empty($parameters->source)) {
            $message = 'No source supplied to scp function.';
        }
        if (empty($parameters->destination)) {
            $message = 'No destination supplied to scp function.';
        }
        if ( ! empty($parameters->log)) {
            $log = $parameters->log;
        } else {
            $log = new stdClass();
        }
        if ( ! empty($parameters->discovery_id)) {
            $log->discovery_id = $parameters->discovery_id;
        }
        $timeout = 0;
        if ( ! empty($parameters->timeout)) {
            $timeout = intval($parameters->timeout);
        } else {
            if ( ! empty($CI->config->config['discovery_ssh_timeout'])) {
                $timeout = intval($CI->config->config['discovery_ssh_timeout']);
            }
        }
        $log->severity = 7;
        $log->file = 'ssh_helper';
        $log->function = 'scp';
        $log->command = '';
        $log->command_output = '';
        $log->ip = $parameters->ip;
        if ( ! empty($message)) {
            $log->message = $message;
            $log->severity = 3;
            discovery_log($log);
            $log->severity = 7;
            return false;
        }
        $ip = $parameters->ip;
        $credentials = $parameters->credentials;
        $source = $parameters->source;
        $destination = $parameters->destination;
        if ( ! empty($parameters->ssh_port)) {
            $ssh_port = intval($parameters->ssh_port);
        }
        if (empty($ssh_port)) {
            $ssh_port = '22';
        }

        set_include_path($CI->config->config['base_path'] . '/code_igniter/application/third_party/phpseclib');
        require_once 'Crypt/RSA.php';
        require_once 'Net/SFTP.php';
        if ( ! defined('NET_SFTP_LOGGING')) {
            define('NET_SFTP_LOGGING', NET_SFTP_LOG_COMPLEX);
        }
        $ssh = new Net_SFTP($ip, $ssh_port);
        if (empty($ssh)) {
            $log->message = 'Could not instanciate SSH object to ' . $ip . ':' . $ssh_port . '.';
            $log->severity = 3;
            discovery_log($log);
            $log->severity = 7;
            return false;
        }
        if ($timeout > 0) {
            $ssh->setTimeout($timeout);
        }
        $key = new Crypt_RSA();
        if ($credentials->type === 'ssh_key') {
            // $log->message = 'Using SSH Key to copy file.';
            // discovery_log($log);
            if ( ! empty($credentials->credentials->password)) {
                $key->setPassword($credentials->credentials->password);
            }
            $key->loadKey($credentials->credentials->ssh_key);
            if ($ssh->login($credentials->credentials->username, $key)) {
                $username = $credentials->credentials->username;
                $password = @$credentials->credentials->password;
            } else {
                $log->message = "Failure, credentials named {$credentials->name} (key) not used to log in to {$ip}.";
                $log->command_status = 'fail';
                $log->command =  $ssh->getLog();
                discovery_log($log);
                return false;
            }
        } else if ($credentials->type === 'ssh') {
            $username = $credentials->credentials->username;
            $password = $credentials->credentials->password;
            $log->message = "Success, credentials named {$credentials->name} used to log in using sftp to {$ip}.";
            $log->command_status = 'success';
            try {
                $ssh->login($credentials->credentials->username, $credentials->credentials->password);
            } catch (Exception $error) {
                $log->message = "Failure, credentials named {$username} not used to log in to {$ip}.";
                $log->command_status = 'fail';
                $log->command =  $ssh->getLog();
                $log->severity = 3;
                return false;
            }
            discovery_log($log);
        } else {
            $log->message = 'No credentials of ssh or ssh_key passed to scp function.';
            $log->command_status = 'fail';
            $log->severity = 3;
            discovery_log($log);
            $log->severity = 7;
            return false;
        }

        $status = true;
        $log->command = 'sftp ' . $source . ' to ' . @$username . '@' . $ip . ':' . $destination;
        $log->command_status = 'success';
        $log->message = 'Copy file to ' . $ip;
        if ($ssh->put($destination, $source, NET_SFTP_LOCAL_FILE) === false) {
            $log->command = '';
            $log->command_output = $ssh->getLog();
            $log->command_status = 'fail';
            $status = false;
        }
        $ssh->disconnect();
        unset($ssh);
        $log->command_time_to_execute = (microtime(true) - $item_start);
        discovery_log($log);
        unset($log->command, $log->command_status, $log->command_time_to_execute, $log->command_output);
        return($status);
    }
}


if ( !  function_exists('scp_get')) {
    /**
     * Copy a file from the target
     * @param  object $parameters Should contain IP, credentials, source, destincation, log
     * @return array  Contains the output and status flag, or false on fail
     */
    function scp_get($parameters)
    {
        $item_start = microtime(true);
        $CI = & get_instance();
        $message = '';
        if (empty($parameters->ip)) {
            $message = 'No IP supplied to scp_get function.';
        } else {
            if ( ! filter_var($parameters->ip, FILTER_VALIDATE_IP)) {
                $message = 'Invalid IP supplied to scp_get function.';
            }
        }
        if ( ! is_object($parameters->credentials)) {
            $message = 'No credentials supplied to scp_get function.';
        }
        if (empty($parameters->source)) {
            $message = 'No source supplied to scp_get function.';
        }
        if (empty($parameters->destination)) {
            $message = 'No destination supplied to scp_get function.';
        }
        if ( ! empty($parameters->log)) {
            $log = $parameters->log;
        } else {
            $log = new stdClass();
            if ( ! empty($parameters->discovery_id)) {
                $log->discovery_id = $parameters->discovery_id;
            }
        }
        $log->ip = @$parameters->ip;
        $log->severity = 7;
        $log->file = 'ssh_helper';
        $log->function = 'scp_get';
        $log->command = '';
        $log->command_output = '';
        if ( ! empty($message)) {
            $log->message = $message;
            $log->command_status = 'fail';
            $log->severity = 3;
            discovery_log($log);
            $log->severity = 7;
            return false;
        }
        $ip = $parameters->ip;
        $credentials = $parameters->credentials;
        $source = $parameters->source;
        $destination = $parameters->destination;
        $ssh_port = '22';
        if ( ! empty($parameters->ssh_port)) {
            $ssh_port = intval($parameters->ssh_port);
        }
        $timeout = 0;
        if ( ! empty($parameters->timeout)) {
            $timeout = intval($parameters->timeout);
        } else {
            if ( ! empty($CI->config->config['discovery_ssh_timeout'])) {
                $timeout = intval($CI->config->config['discovery_ssh_timeout']);
            }
        }

        set_include_path($CI->config->config['base_path'] . '/code_igniter/application/third_party/phpseclib');
        require_once 'Crypt/RSA.php';
        require_once 'Net/SFTP.php';
        if ( ! defined('NET_SSH2_LOGGING')) {
            define('NET_SSH2_LOGGING', 2);
        }
        $ssh = new Net_SFTP($ip, $ssh_port);
        if (empty($ssh)) {
            $log->message = 'Could not instanciate SFTP to ' . $ip . ':' . $ssh_port . '.';
            $log->severity = 3;
            discovery_log($log);
            $log->severity = 7;
            return false;
        }
        if ($timeout > 0) {
            $ssh->setTimeout($timeout);
        }
        $key = new Crypt_RSA();
        if ($credentials->type === 'ssh_key') {
            if ( ! empty($credentials->credentials->password)) {
                $key->setPassword($credentials->credentials->password);
            }
            $key->loadKey($credentials->credentials->ssh_key);
            if ($ssh->login($credentials->credentials->username, $key)) {
                $username = $credentials->credentials->username;
                $password = @$credentials->credentials->password;
            } else {
                $log->message = "Failure, credentials named {$credentials->name} (key) not used to log in to {$ip}.";
                $log->command =  $ssh->getLog();
                discovery_log($log);
            }
        } else if ($credentials->type === 'ssh') {
            $username = $credentials->credentials->username;
            $password = $credentials->credentials->password;
            try {
                $ssh->login($credentials->credentials->username, $credentials->credentials->password);
            } catch (Exception $error) {
                $log->message = "Failure, credentials named {$username} not used to log in to {$ip}.";
                $log->severity = 3;
                discovery_log($log);
                $log->severity = 7;
                return false;
            }
        } else {
            $log->message = 'No credentials of ssh or ssh_key passed to scp_get function.';
            $log->severity = 3;
            discovery_log($log);
            $log->severity = 7;
            return false;
        }

        $log->command = 'sftp ' . @$username . '@' . @$ip . ':' . @$source . ' to ' . $destination;
        $log->command_status = 'success';
        $log->message = 'Copy file from ' . $ip;
        $log->command_status = 'success';
        try {
            $output = $ssh->get($source, $destination);
        } catch (Exception $error) {
            $log->command = $ssh->getLog();
            $log->command_output = $output;
            $log->command_status = 'fail';
            $log->message = 'Attempt to copy file from ' . $ip;
            $output = false;
        }
        $ssh->disconnect();
        unset($ssh);
        $log->command_time_to_execute = (microtime(true) - $item_start);
        if ($log->command_status === 'success') {
            $log->command_output = $output;
            if (empty($output)) {
                $log->command_output = 1;
            }
        }
        discovery_log($log);
        unset($log->command, $log->command_status, $log->command_time_to_execute, $log->command_output);
        return($output);
    }
}

if ( !  function_exists('ssh_command')) {
    /**
     * Run a command on the target, using SSH
     * @param  object $parameters Should contain ip (string), credentials (array of objects), command (string), discovery_id (int)
     * @return false || array Returns an array containing the output or false on fail
     */
    function ssh_command($parameters)
    {
        $item_start = microtime(true);
        $CI = & get_instance();
        if (empty($parameters) OR empty($parameters->ip) OR empty($parameters->credentials) OR empty($parameters->command)) {
            $mylog = new stdClass();
            $mylog->message = 'Function ssh_command called without params object';
            $mylog->severity = 4;
            $mylog->status = 'fail';
            $mylog->file = 'discovery_helper';
            $mylog->function = 'ssh_command';
            stdlog($mylog);
            return;
        }
        if (empty($parameters->log)) {
            $log = new stdClass();
            if ( ! empty($parameters->discovery_id)) {
                $log->discovery_id = $parameters->discovery_id;
            }
        } else {
            $log = $parameters->log;
        }
        $log->file = 'ssh_helper';
        $log->function = 'ssh_command';
        $log->ip = $parameters->ip;

        $ip = $parameters->ip;
        $credentials = $parameters->credentials;
        $command = $parameters->command;
        if ( ! empty($parameters->ssh_port)) {
            $ssh_port = intval($parameters->ssh_port);
        }
        if (empty($ssh_port)) {
            $ssh_port = '22';
        }
        $timeout = 0;
        if ( ! empty($parameters->timeout)) {
            $timeout = intval($parameters->timeout);
        } else {
            if ( ! empty($CI->config->config['discovery_ssh_timeout'])) {
                $timeout = intval($CI->config->config['discovery_ssh_timeout']);
            }
        }
        if ( ! filter_var($ip, FILTER_VALIDATE_IP)) {
            $log->message = 'Invalid IP supplied to ssh_command function.';
            $log->severity = 5;
            $log->command_status = 'fail';
            discovery_log($log);
            return false;
        }
        if ( ! is_object($credentials)) {
            $log->message = 'No credentials supplied to ssh_command function.';
            $log->severity = 5;
            $log->command_status = 'fail';
            discovery_log($log);
            return false;
        }

        set_include_path($CI->config->config['base_path'] . '/code_igniter/application/third_party/phpseclib');
        require_once 'Crypt/RSA.php';
        require_once 'Net/SSH2.php';
        if ( ! defined('NET_SSH2_LOGGING')) {
            define('NET_SSH2_LOGGING', 2);
        }
        $ssh = new Net_SSH2($ip, $ssh_port);
        if (empty($ssh)) {
            $log->message = 'Could not instanciate SSH object to ' . $ip . ':' . $ssh_port . '.';
            $log->severity = 3;
            discovery_log($log);
            $log->severity = 7;
            return false;
        }

        $key = new Crypt_RSA();
        if ($credentials->type === 'ssh_key') {
            if ( ! empty($credentials->credentials->password)) {
                $key->setPassword($credentials->credentials->password);
            }
            $key->loadKey($credentials->credentials->ssh_key);
            if ($ssh->login($credentials->credentials->username, $key)) {
                $username = $credentials->credentials->username;
                $password = @$credentials->credentials->password;
                if ( ! empty($credentials->credentials->sudo_password)) {
                    $password = $credentials->credentials->sudo_password;
                }
            } else {
                $log->message = "Failure, credentials named {$credentials->name} (key) not used to log in to {$ip}.";
                $log->command = 'ssh login attempt to run - ' . $command;
                $log->command_output = $ssh->getLastError();
                $log->command_status = 'fail';
                $log->severity = 5;
                discovery_log($log);
                return false;
            }
        } else if ($credentials->type === 'ssh') {
            if ($ssh->login($credentials->credentials->username, $credentials->credentials->password)) {
                $username = $credentials->credentials->username;
                $password = $credentials->credentials->password;
            } else {
                $log->message = "Failure, credentials named {$credentials->name} not used to log in to {$ip}.";
                $log->command = 'ssh login attempt to run - ' . $command;
                $log->command_output = json_encode($ssh->getLog());
                $log->command_status = 'fail';
                $log->severity = 5;
                discovery_log($log);
                return false;
            }
        } else {
            $log->message = 'No credentials of ssh or ssh_key passed to ssh_command function.';
            $log->severity = 4;
            $log->command_status = 'fail';
            discovery_log($log);
            return false;
        }

        $log->command = $command;
        $log->command_status = '';
        $log->message = 'Executing SSH command';
        $item_start = microtime(true);
        if (strpos($command, 'sudo') === false) {
            $ssh->setTimeout($timeout);
            // Not using sudo, so no password prompt
            $result = $ssh->exec($command);
            $result = explode("\n", $result);
            // remove the last line as it's always blank
            unset($result[count($result)-1]);
        } else {
            // Using sudo - need to input in response to password prompt
            $ssh->setTimeout(1);
            $ssh->write($command . "\n");
            $output = $ssh->read('assword');
            if (stripos($output, 'assword') !== false) {
                $ssh->write($password."\n");
                $output = $ssh->read('[prompt]');
            }
            while ( true ) {
                $output .= $ssh->read('[prompt]');
                if (stripos($output, 'Audit Completed') !== false) {
                    break;
                }
                if ((microtime(true) - $item_start) > $timeout) {
                    break;
                }
            }
            $result = explode("\n", $output);
        }
        $ssh->disconnect();
        unset($ssh);
        for ($i=0; $i < count($result); $i++) {
            $result[$i] = trim($result[$i]);
        }
        $log->command_time_to_execute = (microtime(true) - $item_start);
        $log->command_output = @json_encode($result);
        $log->command_status = 'success';
        discovery_log($log);
        unset($log);
        return($result);
    }
}

if ( !  function_exists('ssh_audit')) {
    /**
     * [ssh_audit description]
     * @param  [type] $parameters [description]
     * @return [type]             [description]
     */
    function ssh_audit($parameters)
    {
        $CI = & get_instance();
        if (empty($parameters) OR empty($parameters->credentials) OR empty($parameters->ip)) {
            $mylog = new stdClass();
            $mylog->severity = 4;
            $mylog->status = 'fail';
            $mylog->message = 'Function ssh_audit called without correct params object';
            $mylog->file = 'ssh_helper';
            $mylog->function = 'ssh_audit';
            stdlog($mylog);
            return;
        }
        if (empty($parameters->log)) {
            $log = new stdClass();
            if ( ! empty($parameters->discovery_id)) {
                $log->discovery_id = $parameters->discovery_id;
            }
        } else {
            $log = $parameters->log;
        }
        $log->severity = 7;
        $log->file = 'ssh_helper';
        $log->function = 'ssh_audit';
        $log->message = 'SSH audit starting';
        $log->command_status = 'notice';
        $log->ip = $parameters->ip;
        $log->system_id = '';
        if ( ! empty($parameters->system_id)) {
            $log->system_id = $parameters->system_id;
        }

        discovery_log($log);
        if ( ! empty($parameters->type)) {
            $type = $parameters->type;
        } else {
            $type = 'subnet';
        }

        if (is_array($parameters->credentials)) {
            $credentials = $parameters->credentials;
        } else {
            $log->message = 'Credentials supplied to ssh_audit not in array format.';
            $log->command_status = 'fail';
            $log->severity = 5;
            discovery_log($log);
            return false;
        }

        if (filter_var($parameters->ip, FILTER_VALIDATE_IP)) {
            $ip = $parameters->ip;
        } else {
            $log->message = 'Invalid IP supplied to ssh_audit function. Supplied IP is: ' . (string)$ip;
            $log->command_status = 'fail';
            $log->severity = 5;
            discovery_log($log);
            return false;
        }
        if ( ! empty($parameters->ssh_port)) {
            $ssh_port = intval($parameters->ssh_port);
        }
        if (empty($ssh_port)) {
            $ssh_port = '22';
        }
        $timeout = 0;
        if ( ! empty($parameters->timeout)) {
            $timeout = intval($parameters->timeout);
        } else {
            if ( ! empty($CI->config->config['discovery_ssh_timeout'])) {
                $timeout = intval($CI->config->config['discovery_ssh_timeout']);
            }
        }

        set_include_path($CI->config->config['base_path'] . '/code_igniter/application/third_party/phpseclib');
        include_once('Crypt/RSA.php');
        include_once('Net/SSH2.php');
        if ( ! defined('NET_SSH2_LOGGING')) {
            define('NET_SSH2_LOGGING', 2);
        }

        foreach ($credentials as $credential) {
            $ssh = new Net_SSH2($ip, $ssh_port);
            // This is only for login. If the target cannot respond within 10 seconds, give up
            $ssh->setTimeout(10);
            if ($credential->type === 'ssh_key') {
                $key = new Crypt_RSA();
                if ( ! empty($credential->credentials->password)) {
                    $key->setPassword($credential->credentials->password);
                }
                $key->loadKey($credential->credentials->ssh_key);
                if ($ssh->login($credential->credentials->username, $key)) {
                    $log->message = "Valid credentials for {$credential->type} named {$credential->name} used to log in to {$ip}.";
                    $log->command_status = 'success';
                    discovery_log($log);
                    $username = $credential->credentials->username;
                    $password = @$credential->credentials->password;
                    if ( ! empty($credential->credentials->sudo_password)) {
                        $password = $credential->credentials->sudo_password;
                    }
                    break;
                } else {
                    $log->message = "Credential set for {$credential->type} named {$credential->name} not working on {$ip}.";
                    $log->command_status = 'notice';
                    discovery_log($log);
                    $ssh->disconnect();
                    unset($ssh);
                }
            } else if ($credential->type === 'ssh') {
                if ($ssh->login($credential->credentials->username, $credential->credentials->password)) {
                    $log->message = "Valid credentials named {$credential->name} used to log in to {$ip}.";
                    $log->command_status = 'success';
                    discovery_log($log);
                    $username = $credential->credentials->username;
                    $password = @$credential->credentials->password;
                    break;
                } else {
                    $log->message = "Credential set for SSH named {$credential->name} not working on {$ip}.";
                    $log->command_status = 'notice';
                    discovery_log($log);
                    $ssh->disconnect();
                    unset($ssh);
                }
            }
        }

        if (empty($username)) {
            $log->command = '';
            $log->command_output = '';
            $log->command_status = 'warning';
            $log->message = "SSH detected but no valid SSH credentials for {$ip}.";
            discovery_log($log);
            return false;
        }

        $device = new stdClass();

        $windows_os_name = $ssh->exec('wmic os get name');
        if (stripos($windows_os_name, 'Microsoft Windows') !== false) {
            $device->type = 'computer';
            $device->os_group = 'Windows';

            $temp = str_replace('Name', '', $windows_os_name);
            $temp = trim($temp);
            $temp = explode('|', $temp);
            $device->os_name = trim($temp[0]);
            unset($temp);
            $log->command = 'wmic os get name; # os_name';
            $log->command_output = $device->os_name;
            $log->command_status = 'success';
            $log->message = 'SSH command';
            discovery_log($log);

            $temp = $ssh->exec('wmic path win32_computersystemproduct get uuid');
            $temp = str_replace('UUID', '', $temp);
            $device->uuid = trim($temp);
            unset($temp);
            $log->command = 'wmic path win32_computersystemproduct get uuid; # uuid';
            $log->command_output = $device->uuid;
            $log->command_status = 'success';
            $log->message = 'SSH command';
            if (empty($device->uuid)) {
                $log->command_status = 'notice';
            }
            discovery_log($log);

            $temp = $ssh->exec('wmic path win32_computersystemproduct get IdentifyingNumber');
            $temp = str_replace('IdentifyingNumber', '', $temp);
            $device->serial = trim($temp);
            unset($temp);
            $log->command = 'wmic path win32_computersystemproduct get IdentifyingNumber; # serial';
            $log->command_output = $device->serial;
            $log->command_status = 'success';
            $log->message = 'SSH command';
            if (empty($device->serial)) {
                $log->command_status = 'notice';
            }
            discovery_log($log);


            $temp = $ssh->exec('wmic computersystem get name');
            $temp = str_replace('Name', '', $temp);
            $device->hostname = strtolower(trim($temp));
            $device->name = $device->hostname;
            unset($temp);
            $log->command = 'wmic computersystem get name; # hostname';
            $log->command_output = $device->hostname;
            $log->command_status = 'success';
            $log->message = 'SSH command';
            if (empty($device->hostname)) {
                $log->command_status = 'notice';
            }
            discovery_log($log);

            $device->os_family = '';
            if (strpos($device->os_name, ' 95') !== false) {
                $device->os_family = 'Windows 95';
            }
            if (strpos($device->os_name, ' 98') !== false) {
                $device->os_family = 'Windows 98';
            }
            if (strpos($device->os_name, ' NT') !== false) {
                $device->os_family = 'Windows NT';
            }
            if (strpos($device->os_name, '2000') !== false) {
                $device->os_family = 'Windows 2000';
            }
            if (strpos($device->os_name, ' XP') !== false) {
                $device->os_family = 'Windows XP';
            }
            if (strpos($device->os_name, '2003') !== false) {
                $device->os_family = 'Windows 2003';
            }
            if (strpos($device->os_name, 'Vista') !== false) {
                $device->os_family = 'Windows Vista';
            }
            if (strpos($device->os_name, '2008') !== false) {
                $device->os_family = 'Windows 2008';
            }
            if (strpos($device->os_name, 'Windows 7') !== false) {
                $device->os_family = 'Windows 7';
            }
            if (strpos($device->os_name, 'Windows 8') !== false) {
                $device->os_family = 'Windows 8';
            }
            if (strpos($device->os_name, '2012') !== false) {
                $device->os_family = 'Windows 2012';
            }
            if (strpos($device->os_name, 'Windows 10') !== false) {
                $device->os_family = 'Windows 10';
            }
            if (strpos($device->os_name, '2016') !== false) {
                $device->os_family = 'Windows 2016';
            }
            $device->credentials = $credential;
            return $device;
        }

        // Before we attempt to run commands, test if we're running bash
        $item_start = microtime(true);
        $device->shell = trim($ssh->exec('echo $SHELL'));
        $device->bash = '';
        $log->command = 'echo $SHELL';
        $log->command_output = $device->shell;
        $log->command_time_to_execute = (microtime(true) - $item_start);
        $log->command_status = 'success';
        $log->message = 'The default shell for ' . $username . ' is ' . $device->shell;
        if (strpos($device->shell, 'bash') === false) {
            $log->command_status = 'notice';
            $log->message = 'The default shell for ' . $username . ' is ' . $device->shell . ' (not bash)';
            $log->severity = 6;  
        }
        discovery_log($log);
        $log->severity = 7;

        if (strpos($device->shell, 'bash') === false) {
            $item_start = microtime(true);
            $device->bash = trim($ssh->exec('which bash'));
            $log->command = 'which bash';
            $log->command_output = $device->bash;
            $log->command_time_to_execute = (microtime(true) - $item_start);
            $log->command_status = 'success';
            if ( ! empty($device->bash) && stripos($device->bash, 'Command not found') === false) {
                $log->message = 'Bash installed';
            } else {
                $log->message = 'Bash not installed';
                $log->command_status = 'notice';
                $log->severity = 6;
                $device->bash = '';
            }
            discovery_log($log);
        }
        $log->severity = 7;

        if ($device->bash === '') {
            // See if we have /bin/sh
            $item_start = microtime(true);
            $device->sh = trim($ssh->exec('which sh'));
            $log->command = 'which sh';
            $log->command_output = $device->sh;
            $log->command_time_to_execute = (microtime(true) - $item_start);
            $log->command_status = 'success';
            if ( ! empty($device->sh) && stripos($device->sh, 'Command not found') === false) {
                $log->message = 'SH installed';
                $device->bash = '/bin/sh';
                $device->shell = '/bin/sh';
            } else {
                $log->message = 'Bash not installed';
                $log->command_status = 'notice';
                $log->severity = 6;
                $device->bash = '';
            }
            discovery_log($log);
            unset($device->sh);
        }

        if (strpos($device->shell, 'bash') === false && $device->bash === '') {
            $log->command = '';
            $log->command_output = $device->shell;
            $log->command_time_to_execute = '';
            $log->severity = 6;
            $log->message = 'Will use ' . $device->shell . ' to run commands. Running commands in a shell other than bash may fail.';
            $log->command_status = 'notice';
            discovery_log($log);
        }
        $log->severity = 7;

        $commands = array(
            'hostname' => 'hostname 2>/dev/null',

            'solaris_domain' => 'domainname 2>/dev/null',

            'osx_serial' => 'system_profiler SPHardwareDataType 2>/dev/null | grep "Serial Number (system):" | cut -d: -f2 | sed "s/^ *//g"',

            'os_group' => 'uname 2>/dev/null',
            'os_name' => 'cat /etc/os-release 2>/dev/null | grep -i ^PRETTY_NAME | cut -d= -f2 | cut -d\" -f2',
            'os_family' => 'cat /etc/os-release 2>/dev/null | grep -i ^NAME | cut -d= -f2 | cut -d\" -f2',
            'os_version' => 'cat /etc/os-release 2>/dev/null | grep -i ^VERSION_ID | cut -d= -f2 | cut -d\" -f2',
            'google_instance_ident' => 'grep instance_id /etc/default/instance_configs.cfg 2>/dev/null | cut -d= -f2',
            'redhat_os_name' => 'cat /etc/redhat-release 2>/dev/null',
            'ubuntu_os_codename' => 'cat /etc/os-release 2>/dev/null | grep -i ^UBUNTU_CODENAME | cut -d= -f2 | cut -d\" -f2',
            'vmware_os_version' => 'uname -r 2>/dev/null',
            'osx_os_version' => 'sw_vers 2>/dev/null | grep "ProductVersion:" | cut -f2',
            'ubiquiti_os' => 'cat /etc/motd 2>/dev/null | grep -i EdgeOS 2>/dev/null',
            'ubiquiti_os_version' => 'cat /etc/version 2>/dev/null',
            'ddwrt_os_name' => 'cat /etc/motd 2>/dev/null | grep -i DD-WRT 2>/dev/null',
            'solaris_os_name' => 'cat /etc/release 2>/dev/null | head -n1 | awk "{print $1, $2, $3}" 2>/dev/null',

            'ddwrt_model' => 'nvram get DD_BOARD 2>/dev/null',
            'ubiquiti_model' => 'cat /etc/board.info 2>/dev/null | grep "board.name" | cut -d= -f2',
            'ubiquiti_serial' => 'grep serialno /proc/ubnthal/system.info 2>/dev/null | cut -d= -f2',

            'dbus_identifier' => 'cat /var/lib/dbus/machine-id 2>/dev/null',
            'bsd_uuid' => 'sysctl -n kern.hostuuid 2>/dev/null',
            'solaris_uuid' => 'smbios -t SMB_TYPE_SYSTEM 2>/dev/null | grep UUID | awk "{print $2}" 2>/dev/null',
            'esx_uuid' => 'vim-cmd hostsvc/hostsummary 2>/dev/null | sed -n "/^   hardware = (vim.host.Summary.HardwareSummary) {/,/^   \},/p" | grep uuid | cut -d= -f2 | sed s/,//g | sed s/\"//g',
            'osx_uuid' => 'system_profiler SPHardwareDataType 2>/dev/null | grep UUID | cut -d: -f2',
            'lshal_uuid' => 'lshal 2>/dev/null | grep "system.hardware.uuid"',

            'bsd_manufacturer' => 'kenv 2>/dev/null | grep smbios.chassis.maker | cut -d\" -f2',
            'bsd_model' => 'kenv 2>/dev/null | grep smbios.planar.product | cut -d\" -f2',
            'bsd_os_family' => 'sysctl -n kern.ostype 2>/dev/null',
            'bsd_os_name' => 'sysctl -n kern.osrelease 2>/dev/null',
            'bsd_os_version' => 'sysctl -n kern.version 2>/dev/null',

            'hpux_hostname' => 'hostname 2>/dev/null',
            'hpux_domain' => 'domainname 2>/dev/null',
            'hpux_os_name' => 'machinfo 2>/dev/null | grep -i "Release:" | cut -d: -f2',
            'hpux_os_group' => 'uname -s 2>/dev/null',
            'hpux_model' => 'model 2>/dev/null',
            'hpux_serial' => 'machinfo 2>/dev/null | grep "Machine serial number:" | cut -d: -f2',
            'hpux_uuid' => 'machinfo 2>/dev/null | grep -i "Machine ID number:" | cut -d: -f2',

            'synology_model' => 'grep model /etc/avahi/services/dsminfo.service 2>/dev/null | cut -d= -f2 | cut -d\< -f1',
            'synology_serial' => 'grep serial /etc/avahi/services/dsminfo.service 2>/dev/null | cut -d= -f2 | cut -d\< -f1',
            'synology_os_major' => 'grep version_major /etc/avahi/services/dsminfo.service 2>/dev/null | cut -d= -f2 | cut -d\< -f1',
            'synology_os_minor' => 'grep version_minor /etc/avahi/services/dsminfo.service 2>/dev/null | cut -d= -f2 | cut -d\< -f1',
            'synology_os_build' => 'grep version_build /etc/avahi/services/dsminfo.service 2>/dev/null | cut -d= -f2 | cut -d\< -f1',

            'which_sudo' => 'which sudo 2>/dev/null',

        );

        if ($type === 'seed') {
            $commands['arp'] = 'arp -an 2>/dev/null';
            $commands['route'] = 'netstat -rn 2>/dev/null | grep "^[0-9]" | awk  \'"\'"\'{print $2}\'"\'"\' | sort | uniq | grep -v "0\.0\.0\.0" | grep "\." | grep -v "127\.0\.0\.1"';
        }

        foreach ($commands as $item => $command) {
            if (strpos($device->shell, 'bash') === false && $device->bash !== '') {
                $command = $device->bash . " -c '" . $command . "'";
            }
            $item_start = microtime(true);
            $temp1 = $ssh->exec($command);
            $temp1 = trim($temp1);
            if (stripos($temp1, 'command not found')) {
                $temp1 = '';
            }
            if ($item === 'solaris_domain' && $temp1 === '(none)') {
                $temp1 = '';
            }
            if ( ! empty($temp1)) {
                $log->command_status = 'success';
                if (strpos($temp1, "\n") !== false) {
                    $array1 = explode("\n", $temp1);
                    foreach ($array1 as &$string) {
                        $string = trim($string);
                        if (strpos($string, '=') !== false) {
                            $temp2 = explode('=', $string);
                            $temp2[1] = str_replace("'", '', $temp2[1]);
                            $temp2[1] = str_replace('"', '', $temp2[1]);
                            @$device->{$item}->{$temp2[0]} = $temp2[1];
                        } else {
                            $device->{$item}[] = $string;
                        }
                    }
                } else {
                    $device->$item = $temp1;
                }
                $log->command = $command;
                $log->command_time_to_execute = (microtime(true) - $item_start);
                $log->command_output = $temp1;
                $log->command_status = 'success';
                $log->message = 'SSH command - ' . $item;
                discovery_log($log);
            } else {
                $log->command = $command;
                $log->command_time_to_execute = (microtime(true) - $item_start);
                $log->command_output = $temp1;
                $log->command_status = 'notice';
                $log->message = 'SSH command - ' . $item;
                discovery_log($log);
            }
        }

        // ARP for other devices
        $device->ips_found = array();

        if ($type === 'seed') {
            if ( ! empty($device->arp)) {
                foreach ($device->arp as $line) {
                    $item = array();

                    $explode = explode('(', $line);
                    $explode_2 = explode(')', $explode[1]);
                    $item_ip = $explode_2[0];

                    $item_mac = '';
                    $explode = explode(' ', $line);
                    if ( ! empty($explode[3])) {
                        $item_mac = strtolower($explode[3]);
                    }
                    if ( ! empty($item_mac) && stripos($item_mac, ':') !== false && $item_mac !== 'ff:ff:ff:ff:ff:ff' &&
                            ! empty($item_ip) && stripos($item_ip, '.') !== false && $item_ip !== '255.255.255.255' && filter_var($item_ip, FILTER_VALIDATE_IP)) {
                        $device->ips_found[$item_mac] = $item_ip;
                    }
                }
            }
            unset($device->arp);
            if ( ! empty($device->route)) {
                foreach ($device->route as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $device->ips_found[] = $ip;
                    }
                }
            }
            unset($device->route);

            // Lower case all MAC addresses
            $device->ips_found = array_change_key_case($device->ips_found, CASE_LOWER);
            // Only need one unique IP
            $device->ips_found = array_unique($device->ips_found);

            $log->command_time_to_execute = '';
            $log->message = 'Seed. All IPs detected using SSH.';
            $log->command = 'Combined SSH arp and route.';
            $log->command_status = 'notice';
            $log->command_output = json_encode($device->ips_found);
            discovery_log($log);
            unset($log->id, $log->command, $log->command_time_to_execute);
        }


        // Set some items that may have multiple results
        if ( ! empty($device->hostname)) {
            $device->hostname = strtolower($device->hostname);
            if (stripos($device->hostname, '.') !== false) {
                $device->fqdn = $device->hostname;
                $temp = explode('.', $device->hostname);
                $device->hostname = $temp[0];
                unset($temp[0]);
                $device->domain = implode('.', $temp);
            }
            $device->name = $device->hostname;
        }
        if (empty($device->domain) && ! empty($device->solaris_domain) && $device->solaris_domain !== '(none)') {
            $device->domain = $device->solaris_domain;
        }
        unset($device->solaris_domain);
        if (empty($device->fqdn) && ! empty($device->hostname) && ! empty($device->domain)) {
            $device->fqdn = $device->hostname . '.' . $device->domain;
        }

        if ( ! empty($device->google_instance_ident) && empty($device->instance_ident)) {
            $device->instance_ident = $device->google_instance_ident;
            unset($device->google_instance_ident);
        }

        if ( ! empty($device->ubiquiti_os)) {
            #$device->os_family = 'Ubiquiti';
            $device->os_group = '';
            $device->manufacturer = 'Ubiquiti Networks Inc.';
        }
        unset($device->ubiquiti_os);
        if ( ! empty($device->ubiquiti_serial)) {
            #$device->os_family = 'Ubiquiti';
            $device->manufacturer = 'Ubiquiti Networks Inc.';
            $device->serial = $device->ubiquiti_serial;
        }
        unset($device->ubiquiti_serial);
        if ( ! empty($device->ubiquiti_os_version)) {
            #$device->os_family = 'Ubiquiti';
            $device->manufacturer = 'Ubiquiti Networks Inc.';
            $device->description = $device->ubiquiti_os_version;
            #$device->os_version = $device->ubiquiti_os_version;
        }
        unset($device->ubiquiti_os_version);
        if ( ! empty($device->ubiquiti_model)) {
            #$device->os_family = 'Ubiquiti';
            $device->manufacturer = 'Ubiquiti Networks Inc.';
            $device->model = $device->ubiquiti_model;
        }
        unset($device->ubiquiti_model);

        if ( ! empty($device->ubuntu_os_codename)) {
            // $device->os_name = $device->os_name . ' (' . $device->ubuntu_os_codename . ')'; // removed to match audit script
        }
        unset($device->ubuntu_os_codename);

        if ( ! empty($device->redhat_os_name)) {
            // $device->os_name = $device->redhat_os_name; # removed to match audit script
            if (stripos($device->redhat_os_name, 'centos') !== false) {
                $device->os_family = 'CentOS';
            }
            if (stripos($device->redhat_os_name, 'fedora') !== false) {
                $device->os_family = 'Fedora';
            }
            if (stripos($device->redhat_os_name, 'redhat') !== false OR stripos($device->redhat_os_name, 'red hat') !== false) {
                $device->os_family = 'RedHat';
            }
            $device->type = 'computer';
        }
        unset($device->redhat_os_name);

        if (stripos($device->os_group, 'VMkernel') !== false && ! empty($device->vmware_os_version)) {
            $device->os_group = 'VMware';
            $device->os_family = 'VMware ESXi';
            $device->os_name = 'Vmware ESXi ' . $device->vmware_os_version;
            $device->class = 'hypervisor';
            $device->type = 'computer';
            unset($device->form_factor);

            $item_start = microtime(true);
            $command = "esxcli hardware platform get | grep 'Product Name' | cut -d: -f2 2>/dev/null";
            $result = trim($ssh->exec($command));
            $log->command_time_to_execute = (microtime(true) - $item_start);
            $log->message = 'SSH command - VMware model';
            $log->command = $command;
            if ( ! empty($result)) {
                $device->model = trim($result);
                $log->command_status = 'success';
                $log->command_output = $device->model;
            } else {
                $log->command_status = 'warning';
                $log->command_output = '';
                unset($device->model);
            }
            discovery_log($log);
            unset($result, $command);

            $log->command_time_to_execute = 0;
            $log->message = 'OS Name';
            $log->command = '';
            $log->command_status = 'success';
            $log->command_output = $device->os_name;
            discovery_log($log);

            $item_start = microtime(true);
            $command = "esxcli hardware platform get | grep 'Vendor Name' | cut -d: -f2 2>/dev/null";
            $result = trim($ssh->exec($command));
            $log->command_time_to_execute = (microtime(true) - $item_start);
            $log->message = 'SSH command - VMware manufacturer';
            $log->command = $command;
            if ( ! empty($result)) {
                $device->manufacturer = trim($result);
                $log->command_status = 'success';
                $log->command_output = $device->manufacturer;
            } else {
                $log->command_status = 'warning';
                $log->command_output = '';
                unset($device->manufacturer);
            }
            discovery_log($log);
            unset($result, $command);

            $item_start = microtime(true);
            $command = "smbiosDump | sed -n '/^  Chassis Info:/,/^  [A-Za-z]/p' | grep '    Type' | cut -d\":\" -f2 | cut -d\" \" -f3 | sed 's/\"//g' | sed 's/(//g' | sed 's/)//g'";
            $result = $ssh->exec($command);
            $log->command = $command;
            $log->command_time_to_execute = (microtime(true) - $item_start);
            $log->message = 'SSH command - VMware form factor';
            if ( ! empty($result)) {
                $device->form_factor = trim($result);
                $log->command_status = 'success';
                $log->command_output = $device->form_factor;
            } else {
                $log->command_status = 'warning';
                $log->command_output = '';
                unset($device->manufacturer);
            }
            discovery_log($log);
            unset($result, $command);
        }
        unset($device->vmware_os_version);

        if (strtolower($device->os_group) === 'darwin') {
            $device->type = 'computer';
            $device->os_family = 'Apple OSX';
            if ( ! empty($device->osx_os_version)) {
                $device->os_name = 'Apple OSX ' . $device->osx_os_version;
            } else {
                $device->os_name = 'Apple OSX';
            }
            if (empty($device->os_version) && ! empty($device->osx_os_version)) {
                $device->os_version = $device->osx_os_version;
            }
        }
        unset($device->osx_os_version);
        if (empty($device->serial) && ! empty($device->osx_serial)) {
            $device->serial = $device->osx_serial;
            if (strlen($device->serial) === 11) {
                $device->manufacturer_code = substr($device->serial, -3);
            }
            if (strlen($device->serial) === 12) {
                $device->manufacturer_code = substr($device->serial, -4);
            }
        }
        unset($device->osx_serial);
        // DD-WRT items
        if (empty($device->os_group) && ! empty($device->ddwrt_os_name)) {
            $device->os_family = 'DD-WRT';
            $device->os_name = trim($device->ddwrt_os_name);
            $device->type = 'router';
        }
        unset($device->ddwrt_os_name);
        if (empty($device->manufacturer) && ! empty($device->ddwrt_model)) {
            $device->manufacturer = $device->ddwrt_model;
        }
        unset($device->ddwrt_model);

        if (empty($device->os_name) && ! empty($device->solaris_os_name)) {
            $device->os_name = trim($device->solaris_os_name);
            $device->type = 'computer';
        }
        unset($device->solaris_os_name);

        if ( ! empty($device->hpux_os_group) && trim($device->hpux_os_group) === 'HP-UX') {
            $device->os_group = 'HP-UX';
            $device->os_family = 'HP-UX';
            $device->type = 'computer';
            $device->class = 'server';
            $device->os_name = trim($device->hpux_os_name);
            $device->uuid = trim($device->hpux_uuid);
            $device->model = trim($device->hpux_model);
            $device->serial = trim($device->hpux_serial);
            $device->hostname = trim($device->hpux_hostname);
            $device->domain = trim($device->hpux_domain);
        }
        unset($device->hpux_os_group);
        unset($device->hpux_uuid);
        unset($device->hpux_model);
        unset($device->hpux_serial);
        unset($device->hpux_hostname);
        unset($device->hpux_domain);
        unset($device->hpux_os_name);

        if ( ! empty($device->synology_model)) {
            $device->system_manufacturer = 'Synology';
            $device->model = 'Diskstation ' . trim($device->synology_model);
            $device->serial = trim($device->synology_serial);
            $device->type = 'nas';
            $device->os_group = 'Linux';
            $device->os_family = 'Synology DSM';
            $device->os_name = 'Synology DSM ' . trim($device->synology_os_major) . '.' . trim($device->synology_os_minor) . '-' . trim($device->synology_os_build);
            $device->os_version = trim($device->synology_os_major) . '.' . trim($device->synology_os_minor);
        }

        // Type based on os_group = Linux (set to computer)
        if (! empty($device->os_group) and $device->os_group === 'Linux' and empty($device->type) and ! empty($device->manufacturer) and $device->manufacturer !== 'Ubiquiti Networks Inc.') {
            $device->type = 'computer';
        }
        if (! empty($device->os_group) and stripos($device->os_group, 'BSD') !== false) {
            $device->type = 'computer';
            $device->model = $device->bsd_model;
            $device->os_group = 'BSD';
            $device->manufacturer = $device->bsd_manufacturer;
            $device->os_family = $device->bsd_os_family;
            $device->os_name = $device->bsd_os_family . ' ' . $device->bsd_os_name;
            $device->os_version = $device->bsd_os_version;
        }
        unset($device->bsd_model);
        unset($device->bsd_manufacturer);
        unset($device->bsd_os_family);
        unset($device->bsd_os_name);
        unset($device->bsd_os_version);

        // UUID
        $array = array('bsd_uuid', 'solaris_uuid', 'esx_uuid', 'osx_uuid', 'lshal_uuid');
        foreach ($array as $attribute) {
            if (empty($device->uuid) && ! empty($device->$attribute)) {
                if ($attribute === 'lshal_uuid') {
                    $temp = explode("'", $device->lshal_uuid);
                    $device->lshal_uuid = $temp[1];
                }
                $device->uuid = $device->$attribute;
            }
            unset($device->$attribute);
        }

        $device->use_sudo = false;
        $command = '';

        if (empty($device->which_sudo) and ! empty($CI->config->config['discovery_sudo_path'])) {
            $sudo_paths = explode(',', $CI->config->config['discovery_sudo_path']);
            foreach ($sudo_paths as $sudo_path) {
                if (strpos($device->shell, 'bash') === false && $device->bash !== '') {
                    $command = $device->bash . " -c 'ls {$sudo_path} 2>/dev/null'";
                } else {
                    $command = "ls {$sudo_path} 2>/dev/null";
                }
                $item_start = microtime(true);
                $temp1 = $ssh->exec($command);
                $temp1 = trim($temp1);
                $log->command = $command;
                $log->command_time_to_execute = (microtime(true) - $item_start);
                $log->message = "SSH command - ls {$sudo_path} 2>/dev/null";
                $log->command_output = $temp1;
                if (!empty($temp1)) {
                    $log->command_status = 'success';
                    if (strpos($temp1, "\n") !== false) {
                        $array1 = explode("\n", $temp1);
                        foreach ($array1 as &$string) {
                            $string = trim($string);
                            $device->which_sudo = $string;
                        }
                    } else {
                        $device->which_sudo = $temp1;
                    }
                    $log->command_status = 'success';
                    discovery_log($log);
                    break;
                } else {
                    $log->command_status = 'notice';
                    discovery_log($log);
                }
            }
        }

        if ($username !== 'root') {
            if (($CI->config->config['discovery_linux_use_sudo'] === 'y' && strtolower($device->os_group) === 'linux') OR
                ($CI->config->config['discovery_sunos_use_sudo'] === 'y' && strtolower($device->os_group) === 'sunos') OR
                (strtolower($device->os_group) !== 'linux' && strtolower($device->os_group) !== 'sunos')) {
                if ( ! empty($device->which_sudo)) {
                    $item_start = microtime(true);
                    $command = $device->which_sudo . ' hostname 2>/dev/null';
                    if (strpos($device->shell, 'bash') === false && $device->bash !== '') {
                        $command = $device->bash . " -c '" . $command . "'\n";
                    } else {
                        $command .= "\n";
                    }
                    $ssh->write($command);
                    $ssh->setTimeout(5);
                    $output = $ssh->read('assword');
                    if (stripos($output, 'assword') !== false) {
                        $ssh->write($password."\n");
                        $output = $ssh->read('[prompt]');
                    }
                    $lines = explode("\n", $output);
                    $hostname = trim($lines[count($lines)-2]);
                    $sudo_temp_hostname = explode('.', $hostname);
                    $ssh_hostname = explode('.', $device->hostname);
                    if (trim(strtolower($sudo_temp_hostname[0])) === trim(strtolower($ssh_hostname[0]))) {
                        $device->use_sudo = true;
                    }
                    $log->command = trim($command) . '; # hostname test using sudo';
                    $log->command_time_to_execute = (microtime(true) - $item_start);
                    $log->command_output = 'sudo hostname: ' . $sudo_temp_hostname[0] . ', Device hostname: ' . $ssh_hostname[0];
                    $log->message = 'SSH command - sudo hostname';
                    if ($device->use_sudo) {
                        $log->command_status = 'success';
                    } else {
                        $log->command_status = 'notice';
                    }
                    discovery_log($log);
                    unset($sudo_temp_hostname, $ssh_hostname, $hostname);
                }
            }
        }

        unset($array);
        if (empty($device->dbus_identifier) && empty($device->uuid) && $username !== 'root') {
            if ($device->use_sudo) {
                // Run DMIDECODE to get the UUID (requires root or sudo)
                $output = '';
                $item_start = microtime(true);
                $command = $device->which_sudo . ' dmidecode -s system-uuid 2>/dev/null';
                if (strpos($device->shell, 'bash') === false && $device->bash !== '') {
                    $command = $device->bash . " -c '" . $command . "'\n";
                } else {
                    $command .= "\n";
                }
                $ssh->write($command);
                $output = $ssh->read('assword');
                if (stripos($output, 'assword') !== false) {
                    $ssh->write($password."\n");
                    $output = $ssh->read('[prompt]');
                }
                $lines = explode("\n", $output);
                $device->uuid = trim($lines[count($lines)-2]);
                if ($device->uuid === ':' OR strpos($device->uuid, 'dmidecode -s system-uuid 2>/dev/null') !== false) {
                    $device->uuid = '';
                }
                $log->command = trim($command) . '; # uuid';
                $log->command_time_to_execute = (microtime(true) - $item_start);
                $log->command_output = '';
                $log->message = 'SSH command';

                if (empty($device->uuid)) {
                    $log->command_status = 'notice';
                    discovery_log($log);

                    // Try to cat a file to get the UUID
                    $output = '';
                    $item_start = microtime(true);
                    $command = $device->which_sudo . ' cat /sys/class/dmi/id/product_uuid 2>/dev/null';
                    if (strpos($device->shell, 'bash') === false && $device->bash !== '') {
                        $command = $device->bash . " -c '" . $command . "'";
                    } else {
                        $command .= "\n";
                    }
                    $ssh->write($command);
                    $output = $ssh->read('assword');
                    if (stripos($output, 'assword') !== false) {
                        $ssh->write($password."\n");
                        $output = $ssh->read('[prompt]');
                    }
                    $lines = explode("\n", $output);
                    $device->uuid = trim($lines[count($lines)-2]);
                    if (stripos($device->uuid, 'cat /sys/class/dmi/id/product_uuid 2>/dev/null') !== false) {
                        $device->uuid = '';
                    }
                    $log->command = trim($command) . '; # uuid';
                    $log->command_time_to_execute = (microtime(true) - $item_start);
                    $log->command_output = '';
                    $log->message = 'SSH command';
                    if ( ! empty($device->uuid)) {
                        $log->command_output = $device->uuid;
                        $log->command_status = 'success';
                    } else {
                        $log->command_status = 'notice';
                    }
                    discovery_log($log);
                } else {
                    $log->command_output = $device->uuid;
                    $log->command_status = 'success';
                    discovery_log($log);
                }
                if (empty($device->uuid)) {
                    unset($device->uuid);
                }
            }
        }

        if (empty($device->uuid) && $username === 'root') {
            $item_start = microtime(true);
            $command = 'dmidecode -s system-uuid 2>/dev/null';
            if (strpos($device->shell, 'bash') === false && $device->bash !== '') {
                $command = $device->bash . " -c '" . $command . "'";
            }
            $device->uuid = trim($ssh->exec($command));
            $log->command_output = json_encode(explode("\n", $device->uuid));

            if (strpos($device->uuid, 'dmidecode -s system-uuid 2>/dev/null') !== false) {
                $device->uuid = '';
            }
            $log->message = 'SSH command';
            $log->command = trim($command) .'; # uuid';
            $log->command_time_to_execute = (microtime(true) - $item_start);
            if ( ! empty($device->uuid)) {
                $log->command_output = $device->uuid;
                $log->command_status = 'success';
                discovery_log($log);
            } else {
                $log->command_output = '';
                $log->command_status = 'notice';
                discovery_log($log);

                $item_start = microtime(true);
                $command = 'cat /sys/class/dmi/id/product_uuid 2>/dev/null';
                if (strpos($device->shell, 'bash') === false && $device->bash !== '') {
                    $command = $device->bash . " -c '" . $command . "'";
                }
                $device->uuid = trim($ssh->exec($command));
                $log->command = trim($command) . '; # uuid';
                $log->message = 'SSH command';
                $log->command_time_to_execute = (microtime(true) - $item_start);
                if (empty($device->uuid)) {
                    $log->command_output = '';
                    $log->command_status = 'notice';
                    discovery_log($log);
                } else {
                    $log->command_output = $device->uuid;
                    $log->command_status = 'success';
                    discovery_log($log);
                }
            }
        }

        $log->command = '';
        $log->command_time_to_execute = '';
        $log->command_output = '';
        $log->command_status = '';
        $log->message = '';
        $device->credentials = $credential;
        $ssh->disconnect();
        unset($ssh);
        return $device;
    }
}

if ( !  function_exists('ssh_create_keyfile')) {
    /**
     * [ssh_create_keyfile description]
     * @param  [type] $key_string [description]
     * @param  string $display    [description]
     * @param  [type] $log        [description]
     * @return [type]             [description]
     */
    function ssh_create_keyfile($key_string, $display = 'n', $log = null)
    {
        if (strtolower($display) !== 'y') {
            $display = 'n';
        } else {
            $display = 'y';
        }
        if (is_null($log)) {
            $log = new stdClass();
        }
        $log->file = 'ssh_helper';
        $log->function = 'ssh_create_keyfile';
        $log->command = '';
        $log->message = 'SSH create keyfile starting';
        discovery_log($log);

        if (empty($key_string)) {
            $log->message = 'No key_string array passed to ssh_create_keyfile.';
            stdlog($log);
            return false;
        }

        $CI = & get_instance();
        $microtime = str_replace(' ', '_', microtime());
        $microtime = str_replace('.', '_', $microtime);
        if (php_uname('s') !== 'Windows NT') {
            $ssh_keyfile = $CI->config->config['base_path'] . '/other/scripts/key_' . $microtime;
        } else {
            $ssh_keyfile = $CI->config->config['base_path'] . '\\other\\scripts\\key_' . $microtime;
        }

        try {
            $fileopen = fopen($ssh_keyfile, 'w');
        } catch (Exception $error) {
            $log->command = "fopen(\$ssh_keyfile, 'w');";
            $log->command_output = $error->getMessage();
            $log->message = 'Could not create keyfile ' . $ssh_keyfile;
            $log->severity = 3;
            $log->command_status = 'fail';
            discovery_log($log);
            return false;
        }
        try {
            chmod($ssh_keyfile, 0600);
        } catch (Exception $error) {
            $log->command = 'chmod($ssh_keyfile, 0600);';
            $log->command_output = $error->getMessage();
            $log->message = 'Could not chmod 0600 keyfile ' . $ssh_keyfile;
            $log->severity = 3;
            $log->command_status = 'fail';
            discovery_log($log);
            return false;
        }
        try {
            fwrite($fileopen, $key_string);
        } catch (Exception $error) {
            $log->command = 'fwrite($fileopen, $key_string);';
            $log->command_output = $error->getMessage();
            $log->message = 'Could not write to keyfile ' . $ssh_keyfile;
            $log->severity = 3;
            $log->command_status = 'fail';
            discovery_log($log);
            return false;
        }
        try {
            fclose($fileopen);
        } catch (Exception $error) {
            $log->command = 'fclose($fileopen);';
            $log->command_output = $error->getMessage();
            $log->message = 'Could not close keyfile ' . $ssh_keyfile;
            $log->severity = 3;
            $log->command_status = 'fail';
            discovery_log($log);
            return false;
        }
        return($ssh_keyfile);
    }
}
// End of file ssh_helper.php
// Location: ./helpers/ssh_helper.php
