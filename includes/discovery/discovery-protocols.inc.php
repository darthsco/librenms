<?php

global $link_exists;

use LibreNMS\Util\IP;

$community = $device['community'];

if ($device['os'] == 'ironware' && $config['autodiscovery']['xdp'] === true) {
    echo ' Brocade FDP: ';
    $fdp_array = snmpwalk_cache_twopart_oid($device, 'snFdpCacheEntry', array(), 'FOUNDRY-SN-SWITCH-GROUP-MIB');
    d_echo($fdp_array);
    if ($fdp_array) {
        unset($fdp_links);
        foreach ($fdp_array as $key => $fdp_if_array) {
            $interface = get_port_by_ifIndex($device['device_id'], $key);
            d_echo($fdp_if_array);
            foreach ($fdp_if_array as $entry_key => $fdp) {
                $remote_device_id = dbFetchCell('SELECT `device_id` FROM `devices` WHERE `sysName` = ? OR `hostname` = ?', array($fdp['snFdpCacheDeviceId'], $fdp['snFdpCacheDeviceId']));

                if (!$remote_device_id &&
                    !can_skip_discovery($config['autodiscovery']['xdp_exclude']['sysname_regexp'], $fdp['snFdpCacheDeviceId'], $fdp['snFdpCacheDeviceId']) &&
                    !can_skip_discovery($config['autodiscovery']['xdp_exclude']['sysdesc_regexp'], $fdp['snFdpCacheVersion'], $fdp['snFdpCacheDeviceId'])
                ) {
                    $remote_device_id = discover_new_device($fdp['snFdpCacheDeviceId'], $device, 'FDP', $interface);
                }

                if ($remote_device_id) {
                    $if             = $fdp['snFdpCacheDevicePort'];
                    $remote_port_id = dbFetchCell('SELECT port_id FROM `ports` WHERE (`ifDescr` = ? OR `ifName` = ?) AND `device_id` = ?', array($if, $if, $remote_device_id));
                } else {
                    $remote_port_id = '0';
                }

                discover_link($interface['port_id'], $fdp['snFdpCacheVendorId'], $remote_port_id, $fdp['snFdpCacheDeviceId'], $fdp['snFdpCacheDevicePort'], $fdp['snFdpCachePlatform'], $fdp['snFdpCacheVersion'], $device['device_id'], $remote_device_id);
            }
        }//end foreach
    }//end if
}//end if

echo ' CISCO-CDP-MIB: ';
unset($cdp_array);
if ($config['autodiscovery']['xdp'] === true) {
    $cdp_array = snmpwalk_cache_twopart_oid($device, 'cdpCache', array(), 'CISCO-CDP-MIB');
    d_echo($cdp_array);
    if ($cdp_array) {
        unset($cdp_links);
        foreach ($cdp_array as $key => $cdp_if_array) {
            $interface = get_port_by_ifIndex($device['device_id'], $key);
            d_echo($cdp_if_array);
            foreach ($cdp_if_array as $entry_key => $cdp) {
                if (is_valid_hostname($cdp['cdpCacheDeviceId']) || ($config['discovery_by_ip'] == true)) {
                    $cdp_ip = IP::fromHexString($cdp['cdpCacheAddress'], true);
                    $remote_device_id = dbFetchCell('SELECT `device_id` FROM `devices` WHERE `sysName` = ? OR `hostname` = ? OR `hostname` = ?', array($cdp['cdpCacheDeviceId'], $cdp['cdpCacheDeviceId'], $cdp_ip));

                    if (!$remote_device_id &&
                        !can_skip_discovery($config['autodiscovery']['cdp_exclude']['platform_regexp'], $cdp['cdpCachePlatform'], $cdp['cdpCacheDeviceId']) &&
                        !can_skip_discovery($config['autodiscovery']['xdp_exclude']['sysname_regexp'], $cdp['cdpCacheDeviceId'], $cdp['cdpCacheDeviceId']) &&
                        !can_skip_discovery($config['autodiscovery']['xdp_exclude']['sysdesc_regexp'], $cdp['cdpCacheVersion'], $cdp['cdpCacheDeviceId'])
                    ) {
                        if ($config['discovery_by_ip'] !== true) {
                            $remote_device_id = discover_new_device($cdp['cdpCacheDeviceId'], $device, 'CDP', $interface);
                        } else {
                            $remote_device_id = discover_new_device($cdp_ip, $device, 'CDP', $interface);
                        }
                    }

                    if ($remote_device_id) {
                        $if             = $cdp['cdpCacheDevicePort'];
                        $remote_port_id = dbFetchCell('SELECT port_id FROM `ports` WHERE (`ifDescr` = ? OR `ifName` = ?) AND `device_id` = ?', array($if, $if, $remote_device_id));
                    } else {
                        $remote_port_id = '0';
                    }

                    if ($interface['port_id'] && $cdp['cdpCacheDeviceId'] && $cdp['cdpCacheDevicePort']) {
                        discover_link($interface['port_id'], 'cdp', $remote_port_id, $cdp['cdpCacheDeviceId'], $cdp['cdpCacheDevicePort'], $cdp['cdpCachePlatform'], $cdp['cdpCacheVersion'], $device['device_id'], $remote_device_id);
                    }
                } else {
                    echo 'X';
                }//end if
            }//end foreach
        }//end foreach
    }//end if
}//end if

unset(
    $lldp_array,
    $cdp_array
);

if ($device['os'] == 'pbn' && $config['autodiscovery']['xdp'] === true) {
    echo ' NMS-LLDP-MIB: ';
    $lldp_array  = snmpwalk_cache_oid($device, 'lldpRemoteSystemsData', array(), 'NMS-LLDP-MIB', 'pbn');
    d_echo($lldp_array);
    if ($lldp_array) {
        unset($lldp_links);
        foreach ($lldp_array as $key => $lldp) {
            d_echo($lldp);
            $interface = get_port_by_ifIndex($device['device_id'], $lldp['lldpRemLocalPortNum']);
            $remote_device_id = dbFetchCell('SELECT `device_id` FROM `devices` WHERE `sysName` = ? OR `hostname` = ?', array($lldp['lldpRemSysName'], $lldp['lldpRemSysName']));

            if (!$remote_device_id &&
                is_valid_hostname($lldp['lldpRemSysName']) &&
                !can_skip_discovery($config['autodiscovery']['xdp_exclude']['sysname_regexp'], $lldp['lldpRemSysName'], $lldp['lldpRemSysName']) &&
                !can_skip_discovery($config['autodiscovery']['xdp_exclude']['sysdesc_regexp'], $lldp['lldpRemSysDesc'], $lldp['lldpRemSysName'])
            ) {
                $remote_device_id = discover_new_device($lldp['lldpRemSysName'], $device, 'LLDP', $interface);
            }

            if ($remote_device_id) {
                $if             = $lldp['lldpRemPortDesc'];
                $id             = $lldp['lldpRemPortId'];
                $remote_port_id = dbFetchCell('SELECT `port_id` FROM `ports` WHERE (`ifDescr` = ? OR `ifName` = ? OR `ifDescr` = ? OR `ifName` = ?) AND `device_id` = ?', array($if, $if, $id, $id, $remote_device_id));
            } else {
                $remote_port_id = '0';
            }

            if (is_numeric($interface['port_id']) && isset($lldp['lldpRemSysName']) && isset($lldp['lldpRemPortId'])) {
                discover_link($interface['port_id'], 'lldp', $remote_port_id, $lldp['lldpRemSysName'], $lldp['lldpRemPortId'], null, $lldp['lldpRemSysDesc'], $device['device_id'], $remote_device_id);
            }
        }//end foreach
    }//end if
} elseif ($config['autodiscovery']['xdp'] === true) {
    echo ' LLDP-MIB: ';
    $lldp_array  = snmpwalk_cache_threepart_oid($device, 'lldpRemoteSystemsData', array(), 'LLDP-MIB');
    d_echo($lldp_array);
    $dot1d_array = snmpwalk_cache_oid($device, 'dot1dBasePortIfIndex', array(), 'BRIDGE-MIB');
    d_echo($dot1d_array);
    if ($lldp_array) {
        $lldp_links = '';
        foreach ($lldp_array as $key => $lldp_if_array) {
            d_echo($lldp_if_array);
            foreach ($lldp_if_array as $entry_key => $lldp_instance) {
                if (is_numeric($dot1d_array[$entry_key]['dot1dBasePortIfIndex'])) {
                    $ifIndex = $dot1d_array[$entry_key]['dot1dBasePortIfIndex'];
                } else {
                    $ifIndex = $entry_key;
                }
                $interface = get_port_by_ifIndex($device['device_id'], $ifIndex);

                d_echo($lldp_instance);
                foreach ($lldp_instance as $entry_instance => $lldp) {
                    $remote_device_id = dbFetchCell('SELECT `device_id` FROM `devices` WHERE `sysName` = ? OR `hostname` = ?', array($lldp['lldpRemSysName'], $lldp['lldpRemSysName']));

                    if (!$remote_device_id && is_valid_hostname($lldp['lldpRemSysName'])) {
                        if (!can_skip_discovery($config['autodiscovery']['xdp_exclude']['sysname_regexp'], $lldp['lldpRemSysName'], $lldp['lldpRemSysName']) &&
                            !can_skip_discovery($config['autodiscovery']['xdp_exclude']['sysdesc_regexp'], $lldp['lldpRemSysDesc'], $lldp['lldpRemSysName'])
                        ) {
                            $remote_device_id = discover_new_device($lldp['lldpRemSysName'], $device, 'LLDP', $interface);
                            if (is_numeric($remote_device_id) === false) {
                                $ptopo_array = snmpwalk_cache_oid($device, 'ptopoConnEntry', array(), 'PTOPO-MIB');
                                d_echo($ptopo_array);
                                foreach ($ptopo_array as $ptopo) {
                                    if (strcmp(trim($ptopo['ptopoConnRemoteChassis']), trim($lldp['lldpRemChassisId'])) == 0) {
                                        $discover_hostname = IP::fromHexString($ptopo['ptopoConnAgentNetAddr'], true);
                                        break;
                                    }
                                }
                                unset(
                                    $ptopo_array,
                                    $ip_arr
                                );
                            }
                            d_echo("Discovering $discover_hostname\n");
                            $remote_device_id = discover_new_device($discover_hostname, $device, 'LLDP', $interface);
                        }
                    }
                    // normalize MAC address if present
                    $remote_port_mac_address = '';
                    if ($lldp['lldpRemPortIdSubtype'] == 'macAddress') {
                        $remote_port_mac_address = str_replace(array(' ', ':', '-'), '', strtolower($lldp['lldpRemPortId']));
                    }
                    // get remote device hostname from db by MAC address and replace lldpRemSysName if absent
                    if (!$remote_device_id && $remote_port_mac_address) {
                        $remote_device_id = dbFetchCell('SELECT `device_id` FROM `ports` WHERE ifPhysAddress = ? AND `deleted` = ?', array($remote_port_mac_address, '0'));
                        if ($remote_device_id) {
                            $remote_device_hostname = dbFetchRow('SELECT `hostname` FROM `devices` WHERE `device_id` = ?', array($remote_device_id));
                        }
                        if ($remote_device_hostname['hostname']) {
                            $lldp['lldpRemSysName'] = $remote_device_hostname['hostname'];
                        }
                    }
                    if ($remote_device_id) {
                        $if             = $lldp['lldpRemPortDesc'];
                        $id             = $lldp['lldpRemPortId'];
                        $remote_port_id = dbFetchCell('SELECT `port_id` FROM `ports` WHERE (`ifDescr` = ? OR `ifName` = ? OR `ifDescr` = ? OR `ifName` = ? OR `ifPhysAddress` = ?) AND `device_id` = ?', array($if, $if, $id, $id, $remote_port_mac_address, $remote_device_id));
                    } else {
                        $remote_port_id = '0';
                    }
                    if (is_numeric($interface['port_id']) && isset($lldp['lldpRemSysName']) && isset($lldp['lldpRemPortId'])) {
                        discover_link($interface['port_id'], 'lldp', $remote_port_id, $lldp['lldpRemSysName'], $lldp['lldpRemPortId'], null, $lldp['lldpRemSysDesc'], $device['device_id'], $remote_device_id);
                    }
                }//end foreach
            }//end foreach
        }//end foreach
    }//end if
    unset(
        $dot1d_array
    );
}//end elseif

unset(
    $lldp_array
);

echo ' OSPF Discovery: ';

if ($config['autodiscovery']['ospf'] === true) {
    echo "enabled\n";
    foreach (dbFetchRows('SELECT DISTINCT(`ospfNbrIpAddr`),`device_id` FROM `ospf_nbrs` WHERE `device_id`=?', array($device['device_id'])) as $nbr) {
        $ip = $nbr['ospfNbrIpAddr'];
        if (match_network($config['autodiscovery']['nets-exclude'], $ip)) {
            echo 'x';
            continue;
        }

        if (!match_network($config['nets'], $ip)) {
            echo 'i';
            continue;
        }

        $name             = gethostbyaddr($ip);
        $remote_device_id = discover_new_device($name, $device, 'OSPF');
    }
} else {
    echo "disabled\n";
}

d_echo($link_exists);

$sql = "SELECT * FROM `links` AS L, `ports` AS I WHERE L.local_port_id = I.port_id AND I.device_id = '".$device['device_id']."'";
foreach (dbFetchRows($sql) as $test) {
    $local_port_id   = $test['local_port_id'];
    $remote_hostname = $test['remote_hostname'];
    $remote_port     = $test['remote_port'];
    d_echo("$local_port_id -> $remote_hostname -> $remote_port \n");

    if (!$link_exists[$local_port_id][$remote_hostname][$remote_port]) {
        echo '-';
        $rows = dbDelete('links', '`id` = ?', array($test['id']));
        d_echo("$rows deleted ");
    }
}

unset(
    $link_exists,
    $sql
);
echo "\n";
