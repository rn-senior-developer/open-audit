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
* @category  Controller
* @package   All
* @author    Mark Unwin <marku@opmantek.com>
* @copyright 2014 Opmantek
* @license   http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
* @version   GIT: Open-AudIT_4.3.2
* @link      http://www.open-audit.org
*/
$timer_start = microtime(true);

if ($this->{'m_'.$this->response->meta->collection}->delete(intval($this->response->meta->id))) {
    $this->response->data = array();
    $temp = new stdClass();
    $temp->type = $this->response->meta->collection;
    $this->response->data[] = $temp;
    unset($temp);
    $this->session->set_flashdata('success', 'Object in ' . $this->response->meta->collection . ' deleted.');
} else {
    log_error('ERR-0013');
    $this->session->set_flashdata('error', 'Object in ' . $this->response->meta->collection . ' not deleted.');
}

$timer_end = microtime(true);
$entry = new stdClass();
$entry->time = ($timer_end - $timer_start);
$entry->detail = 'include_delete::delete';
$entry->time_now = time();
$GLOBALS['timer_log'][] = $entry;

if ($this->response->meta->format === 'json') {
    output($this->response);
} else {
    $log = new stdClass();
    $log->object = $this->response->meta->collection;
    $log->function = strtolower($this->response->meta->collection) . '::' . strtolower($this->response->meta->action);
    $log->severity = 5;
    $log->status = 'success';
    $log->summary = 'finish';
    $log->type = 'access';
    $log->detail = json_encode($this->response->meta);
    stdLog($log);
    redirect($this->response->meta->collection);
}

// End of file include_delete.php
// Location: ./controllers/include_delete.php
