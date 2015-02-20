<?php
error_reporting(1);
//set POST variables

$currentRangeStart = strtotime(date("Y-m-d", strtotime("-10 day")));
$currentRangeEnd = strtotime(date("Y-m-d"));
$jsonRes = isset($_REQUEST['json']) ? TRUE : FALSE;
$startDate = isset($_REQUEST['startDate']) ? strtotime(date("Y-m-d", strtotime($_REQUEST['startDate']))) : $currentRangeStart;
$endDate = isset($_REQUEST['endDate']) ? strtotime(date("Y-m-d", strtotime($_REQUEST['endDate']))) : $currentRangeEnd;
$franchiseId = isset($_REQUEST['frId']) ? $_REQUEST['frId'] : "";

$url = "http://192.168.0.183:5984/service_ptp_upsell_data/_design/find_order_by/_view"
        . "/date?startkey=$startDate&endkey=$endDate&stale=ok";
//open connection
$ch = curl_init();

//set options 
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: multipart/form-data"));
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //needed so that the $result=curl_exec() output is the file and isn't just true/false
//execute post
$result = curl_exec($ch);

//close connection
curl_close($ch);

//write to file
$arr = json_decode($result);
//echo "<pre>";
//print_r($arr);
$data = $arr;
$show_array = array();

$total_services = $total_orig_services = $total_addtl_services = 0;
$total_revenue = $total_orig_revenue = $total_addtl_revenue = 0.0;

$file = fopen('testdat.txt', 'w');
fwrite($file, var_export($data, true));
fclose($file);

$item_codes_values = array();
$upgrade_totals = array(
    'EZPosts Sent' => 0, 'EZPosts Opened' => 0, 'EZPosts Signed by Client' => 0, 'EZPosts Completed' => 0,
    'Plus to Premium' => 0, 'Plus to Prestige' => 0, 'Premium to Prestige' => 0,
);

$original_plus_inspecs = $original_premium_inspecs = $original_prestige_inspecs = 0;
$original_plus_services = $original_premium_services = $original_prestige_services = 0;

$final_plus_inspecs = $final_premium_inspecs = $final_prestige_inspecs = 0;
$final_plus_services = $final_premium_services = $final_prestige_services = 0;
$plus_upgrades_value = $premium_upgrades_value = $prestige_upgrades_value = 0;

$service_prices = array();

$client_addresses_lastnames = $display_rows = array();
$row_index = 0;

foreach ($data->rows as $thisRow) {
    $val = $thisRow->value;
    $client_name = explode(' ', $val->inspectionName);
    $val->clientLastName = $client_name[count($client_name) - 1];

    if (((!empty($franchiseId) && $franchiseId == $val->franchiseId) || empty($franchiseId)) && (((int) $val->franchiseId >= 1000 && $_GET['showtestusers'] != 'yes') || $req_data['zee_data_email'] == '')) {
        if (!isset($client_addresses_lastnames[$val->inspectionAddress]) || !isset($client_addresses_lastnames[$val->inspectionAddress][$val->clientLastName])) {
            $client_addresses_lastnames[$val->inspectionAddress][$val->clientLastName] = $row_index;
            array_push($display_rows, $thisRow);
        } else {
            unset($display_rows[$client_addresses_lastnames[$val->inspectionAddress][$val->clientLastName]]);
            array_push($display_rows, $thisRow);
            $display_rows = array_values($display_rows);
        }
        $row_index++;
    }
}

foreach ($display_rows as $thisRow) {
    $val = $thisRow->value;

    if (is_string($val->items)) {
        $items = json_decode('[' . $val->items . ']');
    } else {
        $items = $val->items;
    }
    if (gettype($val->originalItems) == 'string') {
        $original_items = json_decode('[' . $val->originalItems . ']');
    } else {
        $original_items = $val->originalItems;
    }

    $items_list = array();
    $items_price = 0.0;
    foreach ($items as $this_item) {
        $this_item->ptpid = str_replace(' ', '', $this_item->ptpid);

        if ($val->originalPackageName == $val->packageName || ($this_item->ptpid != 'RS' && $this_item->ptpid != 'IR')) {
            $items_list[] = $this_item->ptpid;
            if (!isset($service_prices[$this_item->ptpid])) {
                $service_prices[$this_item->ptpid] = 0.0;
            }
            $service_prices[$this_item->ptpid] += (float) $this_item->price;
            $items_price += (float) $this_item->price;
            if (isset($item_codes_values[$this_item->ptpid])) {
                $item_codes_values[$this_item->ptpid] += 1;
            } else {
                $item_codes_values[$this_item->ptpid] = 1;
            }
        }
    }

    $orig_items_list = array();
    $orig_items_price = 0.0;
    foreach ($original_items as $this_item) {
        $orig_items_list[] = $this_item->ptpid;
        $orig_items_price += (float) $this_item->price;
    }

    if ($val->orderDisplayStatus == 'upsell') {
        $val->orderDisplayStatus = 'upsell viewed';
    }
    if ($val->orderStatus == 'complete') {
        $val->orderDisplayStatus = 'complete';
    } // HACK to fix bug with unknown cause

    if (!isset($val->orderDisplayStatus)) {
        $val->orderDisplayStatus = $val->orderStatus;
    }
    $show_array[] = array(date("Y-m-d H:i:s", $val->dateCreated), $val->franchiseId, $val->franchiseName, $val->franchiseEmail, $val->inspectionName, $val->InspectionNo, $val->InspectedByEntry, $val->UniqueID, $val->orderDisplayStatus, $val->originalPackageName, $val->packageName, implode(",", $orig_items_list), implode(",", $items_list), number_format($orig_items_price, 2), number_format(($items_price - $orig_items_price), 2), number_format($items_price, 2));

    $total_services += count($items_list);
    $total_orig_services += count($orig_items_list);
    $total_addtl_services += (count($items_list) - count($orig_items_list));

    $total_revenue += $items_price;
    $total_orig_revenue += $orig_items_price;
    $total_addtl_revenue += ($items_price - $orig_items_price);

    if ($val->originalPackageName == 'non3p') {
        $original_non3p_services += count($orig_items_list);
        $original_non3p_inspecs++;
        $original_non3p_revenue += $orig_items_price;
        $final_non3p_services += count($items_list);
        $final_non3p_inspecs++;
        $final_non3p_revenue += $items_price;
    }
    if ($val->originalPackageName == 'plus') {
        $original_plus_services += count($orig_items_list);
        $original_plus_inspecs++;
        $original_plus_revenue += $orig_items_price;
    }
    if ($val->originalPackageName == 'premium') {
        $original_premium_services += count($orig_items_list);
        $original_premium_inspecs++;
        $original_premium_revenue += $orig_items_price;
    }
    if ($val->originalPackageName == 'prestige') {
        $original_prestige_services += count($orig_items_list);
        $original_prestige_inspecs++;
        $original_prestige_revenue += $orig_items_price;
    }
    if ($val->packageName == 'plus') {
        $final_plus_services += count($items_list);
        $final_plus_inspecs++;
        $final_plus_revenue += $items_price;
    }
    if ($val->packageName == 'premium') {
        $final_premium_services += count($items_list);
        $final_premium_inspecs++;
        $final_premium_revenue += $items_price;
    }
    if ($val->packageName == 'prestige') {
        $final_prestige_services += count($items_list);
        $final_prestige_inspecs++;
        $final_prestige_revenue += $items_price;
    }

    // service totals added up here
    $upgrade_totals['EZPosts Sent'] ++;
    $service_fee_totals['EZPosts Sent'] += $items_price;
    if ($val->orderDisplayStatus != 'unviewed') {
        $upgrade_totals['EZPosts Opened'] ++;
        $service_fee_totals['EZPosts Opened'] += $items_price;
    }
    if ($val->orderDisplayStatus == 'signed client') {
        $upgrade_totals['EZPosts Signed by Client'] ++;
        $service_fee_totals['EZPosts Signed by Client'] += $items_price;
    } else if ($val->orderDisplayStatus == 'complete') {
        $upgrade_totals['EZPosts Completed'] ++;
        $service_fee_totals['EZPosts Completed'] += $items_price;
    }

    if ($val->originalPackageName == 'plus' && $val->packageName == 'premium') {
        $upgrade_totals['Plus to Premium'] ++;
        $service_fee_totals['Plus to Premium'] += $items_price;
    }
    if ($val->originalPackageName == 'plus' && $val->packageName == 'prestige') {
        $upgrade_totals['Plus to Prestige'] ++;
        $service_fee_totals['Plus to Prestige'] += $items_price;
    }
    if ($val->originalPackageName == 'premium' && $val->packageName == 'prestige') {
        $upgrade_totals['Premium to Prestige'] ++;
        $service_fee_totals['Premium to Prestige'] += $items_price;
    }
}
// Transaction Data Array End
// 
// 
// 
// percentage calculations for service totals
$upgrade_totals_per['EZPosts Opened'] = (round((float) ($upgrade_totals['EZPosts Opened'] / $upgrade_totals['EZPosts Sent']) * 100, 2));
$upgrade_totals_per['EZPosts Signed by Client'] = (round((float) ($upgrade_totals['EZPosts Signed by Client'] / $upgrade_totals['EZPosts Sent']) * 100, 2));
$upgrade_totals_per['EZPosts Completed'] = (round((float) ($upgrade_totals['EZPosts Completed'] / $upgrade_totals['EZPosts Sent']) * 100, 2));
$upgrade_totals_per['Premium to Prestige'] = (round((float) ($upgrade_totals['Premium to Prestige'] / $upgrade_totals['EZPosts Sent']) * 100, 2));
$upgrade_totals_per['Plus to Premium'] = (round((float) ($upgrade_totals['Plus to Premium'] / $upgrade_totals['EZPosts Sent']) * 100, 2));
$upgrade_totals_per['Plus to Prestige'] = (round((float) ($upgrade_totals['Plus to Prestige'] / $upgrade_totals['EZPosts Sent']) * 100, 2));
$upgrade_totals_per['EZPosts Signed'] = (round((float) ($upgrade_totals['EZPosts Signed'] / $upgrade_totals['EZPosts Sent']) * 100, 2));
$upgrade_totals['Total Upgraded'] = ($upgrade_totals['Plus to Premium'] + $upgrade_totals['Plus to Prestige'] + $upgrade_totals['Premium to Prestige']);
$service_fee_totals['Total Upgraded'] = ($service_fee_totals['Plus to Premium'] + $service_fee_totals['Plus to Prestige'] + $service_fee_totals['Premium to Prestige']);
$upgrade_totals_per['Total Upgraded'] = (round((float) (($upgrade_totals['Plus to Premium'] + $upgrade_totals['Plus to Prestige'] + $upgrade_totals['Premium to Prestige']) / $upgrade_totals['EZPosts Sent']) * 100, 2));

$totals_array = array(
    array('Prestige', $original_prestige_inspecs, $final_prestige_inspecs, ($final_prestige_inspecs - $original_prestige_inspecs), number_format($original_prestige_revenue, 2), number_format($final_prestige_revenue, 2), number_format(($final_prestige_revenue - $original_prestige_revenue), 2), round((float) (($final_prestige_services / ($final_plus_services + $final_premium_services + $final_prestige_services + $final_non3p_services)) * 100), 2), (round((float) ($final_prestige_services / $upgrade_totals['EZPosts Sent']) * 100, 2))),
    array('Premium', $original_premium_inspecs,
        $final_premium_inspecs,
        ($final_premium_inspecs - $original_premium_inspecs),
        number_format($original_premium_revenue, 2),
        number_format($final_premium_revenue, 2),
        number_format(($final_premium_revenue - $original_premium_revenue), 2),
        round((float) (($final_premium_services / ($final_plus_services + $final_premium_services + $final_prestige_services + $final_non3p_services)) * 100), 2),
        (round((float) ($final_premium_services / $upgrade_totals['EZPosts Sent']) * 100, 2))),
    array('Plus', $original_plus_inspecs, $final_plus_inspecs, ($final_plus_inspecs - $original_plus_inspecs), number_format($original_plus_revenue, 2), number_format($final_plus_revenue, 2), number_format(($final_plus_revenue - $original_plus_revenue), 2), round((float) (($final_plus_services / ($final_plus_services + $final_premium_services + $final_prestige_services + $final_non3p_services)) * 100), 2), (round((float) ($final_plus_services / $upgrade_totals['EZPosts Sent']) * 100, 2))),
    array('Non-3P', $original_non3p_inspecs, $final_non3p_inspecs, $original_non3p_revenue, $final_non3p_revenue, "", round((float) (($final_non3p_services / ($final_plus_services + $final_premium_services + $final_prestige_services + $final_non3p_services)) * 100)), 2)
);

$return_obj = new StdClass();
$return_obj->transactions = $show_array;
$return_obj->rev_totals = $totals_array;
$return_obj->serv_totals = array();
$totalTrans = $totalPer = $totalAmt = 0;

$upgrade_total_keys = array_keys($upgrade_totals);
for ($ix = 0; isset($upgrade_total_keys[$ix]); $ix++) {
    if ($upgrade_total_keys[$ix] != 'Total Upgraded') {
        $totalTrans += $upgrade_totals[$upgrade_total_keys[$ix]];
        $totalPer += $upgrade_totals_per[$upgrade_total_keys[$ix]];
        $totalAmt += number_format($service_fee_totals[$upgrade_total_keys[$ix]], 2);
    }
    $return_obj->serv_totals[] = array($upgrade_total_keys[$ix], $upgrade_totals[$upgrade_total_keys[$ix]], $upgrade_totals_per[$upgrade_total_keys[$ix]], number_format($service_fee_totals[$upgrade_total_keys[$ix]], 2));
}

$item_codes_values_keys = array_keys($item_codes_values);
for ($ix = 0; isset($item_codes_values_keys[$ix]); $ix++) {
    $per = (round((float) ($item_codes_values[$item_codes_values_keys[$ix]] / $upgrade_totals['EZPosts Sent']) * 100, 2));
    $totalTrans += $item_codes_values[$item_codes_values_keys[$ix]];
    $totalPer += $per;
    $totalAmt += round($service_prices[$item_codes_values_keys[$ix]], 2);
    $return_obj->serv_totals[] = array($item_codes_values_keys[$ix], $item_codes_values[$item_codes_values_keys[$ix]], $per, round($service_prices[$item_codes_values_keys[$ix]], 2));
}

$grandTotal[0] = 'Total Upgrade and Services';
$grandTotal[1] = $totalTrans;
$grandTotal[2] = "&nbsp;";
$grandTotal[3] = $totalAmt;
$return_obj->serv_totals[] = $grandTotal;
//echo "<pre>";
//Transaction DTAA
//print_r($show_array);
//echo count($show_array);
$html = "<table><tr>"
        . "<tr><td>KEY : </td><td><b>json</b></td><td>if this key is set in REQUEST CALL JSON data will be returned else HTML</td></tr>"
        . "<tr><td>KEY : </td><td><b>startDate</b></td><td>if this key is set in REQUEST CALL this date will be used in query else 2 days back date will be used</td></tr>"
        . "<tr><td>KEY : </td><td><b>endDate</b></td><td>if this key is set in REQUEST CALL this date will be used in query else current date will be used</td></tr>"
        . "<tr><td>KEY : </td><td><b>frId</b></td><td>if this key is set in REQUEST CALL given Franchise Id will be used to fillter data else all transactions irrespective to Franchise Id will be returned</td></tr>"
        . "</table><br/><br/>";

$html .= "<table border=1><tr><td>Heading</td><td>#</td><td>Percentage</td><td>Amount</td></tr>";
foreach ($return_obj->serv_totals as $key => $value) {
    $html .= "<tr>"
            . "<td>$value[0]</td>"
            . "<td>$value[1]</td>"
            . "<td>$value[2]</td>"
            . "<td>$value[3]</td></tr>";
}
$html .= "</table>";


$html .= "<br/><br/><b>NOTE :<b><br/>For now three fields mentioned in document given to us, "
        . "are blank as we don't have data in DB for respective fields.<br/><br/>";
$html .= "<table border=1><tr>"
        . "<td>Date Created</td>"
        . "<td>Franchisee ID</td>"
        . "<td>Franchisee Name</td>"
        . "<td>Franchisee Email</td>"
        . "<td>Inspection Name</td>"
        . "<td>InspectionNo</td>"
        . "<td>InspectedByEntry</td>"
        . "<td>UniqueID</td>"
        . "<td>Status</td>"
        . "<td>Original Package</td>"
        . "<td>Final Package</td>"
        . "<td>Original Services</td>"
        . "<td>Final Services</td>"
        . "<td>Original Price</td>"
        . "<td>Added Fees</td>"
        . "<td>Final Total Price</td>"
        . "</tr>";
foreach ($return_obj->transactions as $key => $value) {
    $html .= "<tr>"
            . "<td>$value[0]</td>"
            . "<td>$value[1]</td>"
            . "<td>$value[2]</td>"
            . "<td>$value[3]</td>"
            . "<td>$value[4]</td>"
            . "<td>$value[5]</td>"
            . "<td>$value[6]</td>"
            . "<td>$value[7]</td>"
            . "<td>$value[8]</td>"
            . "<td>$value[9]</td>"
            . "<td>$value[10]</td>"
            . "<td>$value[11]</td>"
            . "<td>$value[12]</td>"
            . "<td>$value[13]</td>"
            . "<td>$value[14]</td>"
            . "<td>$value[15]</td>"
            . "</tr>";
}
$html .= "</table>";

if (!$jsonRes) {
    echo $html;
} else {
    echo json_encode($return_obj);
}

//$arr1 = json_decode($arr->serviceCodes);
////    print_r($arr);
//foreach ($arr->rows as $key => $value) {
//    echo "<br/>";
//    echo date("Y-m-d H:i:s", $value->value->dateCreated);
//    echo "<br/>";
//    print_r($value);
//    echo "<br/>";
//}
//print_r($arr);
//echo $arr->dateCreated;
//echo date("Y-m-d H", '1359059088');
die;
//namespace Foo\soyuz;

require 'index1.php';

        const FOO = 20;

function foo() {
    echo "This is second function<br/><br/>";
}

class foo {

    static function staticmethod() {
        echo "This is second class static function<br/><br/><br/>";
    }

}

/* Unqualified name */
foo(); // resolves to function Foo\Bar\foo
foo::staticmethod(); // resolves to class Foo\Bar\foo, method staticmethod
echo FOO; // resolves to constant Foo\Bar\FOO

/* Qualified name */
//subnamespace\foo(); // resolves to function Foo\Bar\subnamespace\foo
//subnamespace\foo::staticmethod(); // resolves to class Foo\Bar\subnamespace\foo,
//// method staticmethod
//echo subnamespace\FOO."<br/>"."<br/>"; // resolves to constant Foo\Bar\subnamespace\FOO
//
///* Fully qualified name */
//
//\Foo\soyuz\foo(); // resolves to function Foo\Bar\foo
//\Foo\soyuz\foo::staticmethod(); // resolves to class Foo\Bar\foo, method staticmethod
//echo \Foo\soyuz\FOO."<br/>"."<br/>"; // resolves to constant Foo\Bar\FOO
//foo\bar();
//$animal = new foo\Dog();
//$animal->name = "Bob";
//$animal->age = 7;
//echo $animal->Describe();
//echo $animal->Greet();
?>    
</pre>
