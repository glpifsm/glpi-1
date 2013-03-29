<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2013 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/** @file
* @brief
*/

// Direct access to file
if (strpos($_SERVER['PHP_SELF'],"getDropdownValue.php")) {
   include ('../inc/includes.php');
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
   die("Can not acces directly to this file");
}

Session::checkLoginUser();

// Security
if (!($item = getItemForItemtype($_GET['itemtype']))) {
   exit();
}
$table = $item->getTable();
$datas = array();

$displaywith = false;
if (isset($_GET['displaywith'])) {
   if (is_array($_GET['displaywith'])
       && count($_GET['displaywith'])) {
      $displaywith = true;
   }
}

// No define value
if (!isset($_GET['value'])) {
   $_GET['value'] = '';
}

// No define rand
if (!isset($_GET['rand'])) {
   $_GET['rand'] = mt_rand();
}

if (isset($_GET['condition']) && !empty($_GET['condition'])) {
   $_GET['condition'] = rawurldecode(stripslashes($_GET['condition']));
}

if (!isset($_GET['emptylabel']) || ($_GET['emptylabel'] == '')) {
   $_GET['emptylabel'] = Dropdown::EMPTY_VALUE;
}

// Make a select box with preselected values
if (!isset($_GET["limit"])) {
   $_GET["limit"] = $_SESSION["glpidropdown_chars_limit"];
}

$where = "WHERE 1 ";

if ($item->maybeDeleted()) {
   $where .= " AND `is_deleted` = '0' ";
}
if ($item->maybeTemplate()) {
   $where .= " AND `is_template` = '0' ";
}

$NBMAX = $CFG_GLPI["dropdown_max"];
$LIMIT = "LIMIT 0,$NBMAX";

if ($_GET['searchText']==$CFG_GLPI["ajax_wildcard"]) {
   $LIMIT = "";
}

$where .=" AND `$table`.`id` NOT IN ('".$_GET['value']."'";

if (isset($_GET['used'])) {
   $used = $_GET['used'];

   if (count($used)) {
      $where .= ",'".implode("','",$used)."'";
   }
}

if (isset($_GET['toadd'])) {
   $toadd = $_GET['toadd'];
} else {
   $toadd = array();
}

$where .= ") ";

if (isset($_GET['condition']) && $_GET['condition'] != '') {
   $where .= " AND ".$_GET['condition']." ";
}

if ($item instanceof CommonTreeDropdown) {

   if ($_GET['searchText']!=$CFG_GLPI["ajax_wildcard"]) {
      $where .= " AND `completename` ".Search::makeTextSearch($_GET['searchText']);
   }
   $multi = false;

   // Manage multiple Entities dropdowns
   $add_order = "";

   if ($item->isEntityAssign()) {
      $recur = $item->maybeRecursive();

       // Entities are not really recursive : do not display parents
      if ($_GET['itemtype'] == 'Entity') {
         $recur = false;
      }

      if (isset($_GET["entity_restrict"]) && !($_GET["entity_restrict"]<0)) {
         $where .= getEntitiesRestrictRequest(" AND ", $table, '', $_GET["entity_restrict"],
                                              $recur);

         if (is_array($_GET["entity_restrict"]) && count($_GET["entity_restrict"])>1) {
            $multi = true;
         }

      } else {
         $where .= getEntitiesRestrictRequest(" AND ", $table, '', '', $recur);

         if (count($_SESSION['glpiactiveentities'])>1) {
            $multi = true;
         }
      }

      // Force recursive items to multi entity view
      if ($recur) {
         $multi = true;
      }

      // no multi view for entitites
      if ($_GET['itemtype'] == "Entity") {
         $multi = false;
      }

      if ($multi) {
         $add_order = '`entities_id`, ';
      }

   }

   $query = "SELECT *
             FROM `$table`
             $where
             ORDER BY $add_order `completename`
             $LIMIT";

   if ($result = $DB->query($query)) {
//       echo "<select id='".Html::cleanId("dropdown_".$_GET["myname"].$_GET["rand"])."'
//              name='".$_GET['myname']."' size='1'";
//    
// 
//       if (isset($_GET["on_change"]) && !empty($_GET["on_change"])) {
//          echo " onChange='".stripslashes($_GET["on_change"])."'";
//       }
//       echo ">";

//       if (isset($_GET['searchText'])
//           && ($_GET['searchText'] != $CFG_GLPI["ajax_wildcard"])
//           && ($DB->numrows($result) == $NBMAX)) {
//          echo "<option class='tree' value='0'>--".__('Limited view')."--</option>";
//       }

      if (count($toadd)) {
         foreach ($toadd as $key => $val) {
            array_push($datas, array('id'   => $key,
                                     'text' => $val));
//             echo "<option class='tree' ".($_GET['value']==$key?'selected':'').
//                  " value='$key' title=\"".Html::cleanInputText($val)."\">".
//                   Toolbox::substr($val, 0, $_GET["limit"])."</option>";
         }
      }

      if ($_GET['display_emptychoice']) {
            array_push($datas, array ('id'   => 0,
                                      'text' => $_GET['emptylabel']));
//          echo "<option class='tree' value='0'>".$_GET['emptylabel']."</option>";
      }

//       $outputval = Dropdown::getDropdownName($table, $_GET['value']);
// 
//       if ((Toolbox::strlen($outputval) != 0)
//           && ($outputval != "&nbsp;")) {
// 
//          if (Toolbox::strlen($outputval) > $_GET["limit"]) {
//             // Completename for tree dropdown : keep right
//             $outputval = "&hellip;".Toolbox::substr($outputval, -$_GET["limit"]);
//          }
//          if ($_SESSION["glpiis_ids_visible"]
//              || (Toolbox::strlen($outputval) == 0)) {
//             $outputval .= " (".$_GET['value'].")";
//          }
//          echo "<option class='tree' selected value='".$_GET['value']."'>".$outputval."</option>";
//       }

      $last_level_displayed = array();
      $datastoadd = array();
      if ($DB->numrows($result)) {
         $prev = -1;

         while ($data = $DB->fetch_assoc($result)) {
            $ID        = $data['id'];
            $level     = $data['level'];
            $outputval = $data['name'];

            if ($displaywith) {
               foreach ($_GET['displaywith'] as $key) {
                  if (isset($data[$key])) {
                     $withoutput = $data[$key];
                     if (isForeignKeyField($key)) {
                        $withoutput = Dropdown::getDropdownName(getTableNameForForeignKeyField($key),
                                                                $data[$key]);
                     }
                     if ((strlen($withoutput) > 0) && ($withoutput != '&nbsp;')) {
                        $outputval = sprintf(__('%1$s - %2$s'), $outputval, $withoutput);
                     }
                  }
               }
            }

            if ($multi
                && ($data["entities_id"] != $prev)) {
               if ($prev >= 0) {
                  if (count($datastoadd)) {
                     array_push($datas, array('text'    => Dropdown::getDropdownName("glpi_entities", $prev),
                                             'children' => $datastoadd));
                  }
               }
               $prev = $data["entities_id"];
               // Reset last level displayed :
               $datastoadd = array();
            }

            $class = " class='tree' ";
            $raquo = "&raquo;";

            if ($level == 1) {
               $class = " class='treeroot'";
               $raquo = "";
            }

//             if ($_SESSION['glpiuse_flat_dropdowntree']) {
               $outputval = $data['completename'];
               if ($level > 1) {
                  $class = "";
                  $raquo = "";
                  $level = 0;
               }

//             } else { // Need to check if parent is the good one
//                if ($level > 1) {
//                   // Last parent is not the good one need to display arbo
//                   if (!isset($last_level_displayed[$level-1])
//                       || ($last_level_displayed[$level-1] != $data[$item->getForeignKeyField()])) {
// 
//                      $work_level    = $level-1;
//                      $work_parentID = $data[$item->getForeignKeyField()];
//                      $to_display    = '';
// 
//                      do {
//                         // Get parent
//                         if ($item->getFromDB($work_parentID)) {
//                            $title = $item->fields['completename'];
// 
//                            if (isset($item->fields["comment"])) {
//                               $title = sprintf(__('%1$s - %2$s'), $title, $item->fields["comment"]);
//                            }
//                            $output2 = $item->getName();
//                            if (Toolbox::strlen($output2)>$_GET["limit"]) {
//                               $output2 = Toolbox::substr($output2, 0 ,$_GET["limit"])."&hellip;";
//                            }
// 
//                            $class2 = " class='tree' ";
//                            $raquo2 = "&raquo;";
// 
//                            if ($work_level==1) {
//                               $class2 = " class='treeroot'";
//                               $raquo2 = "";
//                            }
// 
//                            $to_display = "<option disabled value='$work_parentID' $class2
//                                            title=\"".Html::cleanInputText($title)."\">".
//                                          str_repeat("&nbsp;&nbsp;&nbsp;", $work_level).
//                                          $raquo2.$output2."</option>".$to_display;
// 
//                            $last_level_displayed[$work_level] = $item->fields['id'];
//                            $work_level--;
//                            $work_parentID = $item->fields[$item->getForeignKeyField()];
// 
//                         } else { // Error getting item : stop
//                            $work_level = -1;
//                         }
// 
//                      } while (($work_level >= 1)
//                               && (!isset($last_level_displayed[$work_level])
//                                   || ($last_level_displayed[$work_level] != $work_parentID)));
// 
//                      echo $to_display;
//                   }
//                }
//                $last_level_displayed[$level] = $data['id'];
//             }

//             if (Toolbox::strlen($outputval) > $_GET["limit"]) {
// 
//                if ($_SESSION['glpiuse_flat_dropdowntree']) {
//                   $outputval = "&hellip;".Toolbox::substr($outputval, -$_GET["limit"]);
//                } else {
//                   $outputval = Toolbox::substr($outputval, 0, $_GET["limit"])."&hellip;";
//                }
//             }

            if ($_SESSION["glpiis_ids_visible"]
                || (Toolbox::strlen($outputval) == 0)) {
               $outputval = sprintf(__('%1$s (%2$s)'), $outputval, $ID);
            }

            $title = $data['completename'];
            if (isset($data["comment"])) {
               $title = sprintf(__('%1$s - %2$s'), $title, $data["comment"]);
            }
            array_push($datastoadd, array ('id'    => $ID,
                                           'text'  => $outputval));
            
//             echo "<option value='$ID' $class title=\"".Html::cleanInputText($title).
//                  "\">".str_repeat("&nbsp;&nbsp;&nbsp;", $level).$raquo.$outputval.
//                  "</option>";
         }
      }
//       echo "</select>";
   }
   if ($multi) {
      if (count($datastoadd)) {
         array_push($datas, array('text'     => Dropdown::getDropdownName("glpi_entities", $prev),
                                  'children' => $datastoadd));
      }
   } else {
      if (count($datastoadd)) {
         $datas += $datastoadd;
      }
   }
} else { // Not a dropdowntree
   $multi = false;

   if ($item->isEntityAssign()) {
      $multi = $item->maybeRecursive();

      if (isset($_GET["entity_restrict"]) && !($_GET["entity_restrict"] < 0)) {
         $where .= getEntitiesRestrictRequest("AND", $table, "entities_id",
                                              $_GET["entity_restrict"], $multi);

         if (is_array($_GET["entity_restrict"]) && (count($_GET["entity_restrict"]) > 1)) {
            $multi = true;
         }

      } else {
         $where .= getEntitiesRestrictRequest("AND", $table, '', '', $multi);

         if (count($_SESSION['glpiactiveentities'])>1) {
            $multi = true;
         }
      }
   }

   $field = "name";
   if ($item instanceof CommonDevice) {
      $field = "designation";
   }

   if ($_GET['searchText']!=$CFG_GLPI["ajax_wildcard"]) {
      $search = Search::makeTextSearch($_GET['searchText']);
      $where .=" AND  (`$table`.`$field` ".$search;

      if ($_GET['itemtype']=="SoftwareLicense") {
         $where .= " OR `glpi_softwares`.`name` ".$search;
      }
      $where .= ')';
   }

   switch ($_GET['itemtype']) {
      case "Contact" :
         $query = "SELECT `$table`.`entities_id`,
                          CONCAT(`name`,' ',`firstname`) AS $field,
                          `$table`.`comment`, `$table`.`id`
                   FROM `$table`
                   $where";
         break;

      case "SoftwareLicense" :
         $query = "SELECT `$table`.*,
                          CONCAT(`glpi_softwares`.`name`,' - ',`glpi_softwarelicenses`.`name`)
                              AS $field
                   FROM `$table`
                   LEFT JOIN `glpi_softwares`
                        ON (`glpi_softwarelicenses`.`softwares_id` = `glpi_softwares`.`id`)
                   $where";
         break;

      default :
         $query = "SELECT *
                   FROM `$table`
                   $where";
   }

   if ($multi) {
      $query .= " ORDER BY `entities_id`, $field
                 $LIMIT";
   } else {
      $query .= " ORDER BY $field
                 $LIMIT";
   }

   if ($result = $DB->query($query)) {
//       echo "<select id='".Html::cleanId("dropdown_".$_GET["myname"].$_GET["rand"])."'
//              name='".$_GET['myname']."' size='1'";
             
//       if (isset($_GET["on_change"]) && !empty($_GET["on_change"])) {
//          echo " onChange='".stripslashes($_GET["on_change"])."'";
//       }
// 
//       echo ">";

//       if (isset($_GET['searchText'])
//           && ($_GET['searchText'] != $CFG_GLPI["ajax_wildcard"])
//           && ($DB->numrows($result) == $NBMAX)) {
//          echo "<option value='0'>--".__('Limited view')."--</option>";
// 
//       } else
      if (!isset($_GET['display_emptychoice']) || $_GET['display_emptychoice']) {
         array_push($datas, array ('id'    => 0,
                                   'text'  => $_GET["emptylabel"]));
//          echo "<option value='0'>".$_GET["emptylabel"]."</option>";
      }

      if (count($toadd)) {
         foreach ($toadd as $key => $val) {
            array_push($datas, array ('id'    => $key,
                                      'text'  => $val));
         
//             echo "<option title=\"".Html::cleanInputText($val)."\" value='$key' ".
//                   ($_GET['value']==$key?'selected':'').">".
//                   Toolbox::substr($val, 0, $_GET["limit"])."</option>";
         }
      }

      $outputval = Dropdown::getDropdownName($table,$_GET['value']);

//       if ((strlen($outputval) != 0) && ($outputval != "&nbsp;")) {
//          if ($_SESSION["glpiis_ids_visible"]) {
//             $outputval = sprintf(__('%1$s (%2$s)'), $outputval, $_GET['value']);
//          }
//          echo "<option selected value='".$_GET['value']."'>".$outputval."</option>";
//       }

      $datastoadd = array();

      if ($DB->numrows($result)) {
         $prev = -1;

         while ($data =$DB->fetch_assoc($result)) {
            if ($multi
                && ($data["entities_id"] != $prev)) {
               if ($prev >= 0) {
                  if (count($datastoadd)) {
                     array_push($datas, array('text'    => Dropdown::getDropdownName("glpi_entities", $prev),
                                             'children' => $datastoadd));
                  }
               }
               $prev = $data["entities_id"];
               $datastoadd = array();
            }
            
            $outputval = $data[$field];

            if ($displaywith) {
               foreach ($_GET['displaywith'] as $key) {
                  if (isset($data[$key])) {
                     $withoutput = $data[$key];
                     if (isForeignKeyField($key)) {
                        $withoutput = Dropdown::getDropdownName(getTableNameForForeignKeyField($key),
                                                                $data[$key]);
                     }
                     if ((strlen($withoutput) > 0) && ($withoutput != '&nbsp;')) {
                        $outputval = sprintf(__('%1$s - %2$s'), $outputval, $withoutput);
                     }
                  }
               }
            }
            $ID         = $data['id'];
            $addcomment = "";
            $title      = $outputval;
            if (isset($data["comment"])) {
               $title = sprintf(__('%1$s - %2$s'), $title, $data["comment"]);
            }
            if ($_SESSION["glpiis_ids_visible"]
                || (strlen($outputval) == 0)) {
               //TRANS: %1$s is the name, %2$s the ID
               $outputval = sprintf(__('%1$s (%2$s)'), $outputval, $ID);
            }
            array_push($datastoadd, array ('id'    => $ID,
                                           'text'  => $outputval));
//             echo "<option value='$ID' title=\"".Html::cleanInputText($title)."\">".
//                   Toolbox::substr($outputval, 0, $_GET["limit"])."</option>";
         }
         if ($multi) {
            if (count($datastoadd)) {
               array_push($datas, array('text'     => Dropdown::getDropdownName("glpi_entities", $prev),
                                       'children' => $datastoadd));
            }
         } else {
            if (count($datastoadd)) {
               $datas += $datastoadd;
            }
         }
      }
//       echo "</select>";
   }
}

/// TODO
// if (isset($_GET["comment"]) && $_GET["comment"]) {
//    $paramscomment = array('value' => '__VALUE__',
//                           'table' => $table);
// 
//    Ajax::updateItemOnSelectEvent("dropdown_".$_GET["myname"].$_GET["rand"],
//                                  "comment_".$_GET["myname"].$_GET["rand"],
//                                  $CFG_GLPI["root_doc"]."/ajax/comments.php", $paramscomment);
// }
// 
// Ajax::commonDropdownUpdateItem($_GET);
$ret['results'] = $datas;

echo json_encode($ret);
?>
