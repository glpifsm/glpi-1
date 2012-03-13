<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2012 by the INDEPNET Development Team.

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

// ----------------------------------------------------------------------
// Original Author of file: Damien Touraine
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/// Class NetworkName : represent the internet name of an element. It is compose of the name itself,
/// its domain and one or several IP addresses (IPv4 and/or IPv6). It relies on IPAddress object.
/// There is no network associated with the addresses as the addresses can be inside several
/// different kind of networks : at least one real network (ie : the one that is configured in the
/// computer with gateways) and several administrative networks (for instance, entity sub-network).
/// An address can be affected to an item, or can be "free" to be reuse by another item (for
/// instance, in case of maintenance, when you change the network card of a computer, but not its
/// network information
/// since version 0.84
class NetworkName extends FQDNLabel {

   // From CommonDBChild
   public $itemtype              = 'itemtype';
   public $items_id              = 'items_id';
   public $dohistory             = true;
   public $inheritEntityFromItem = true;


   function canCreate() {

      if (!Session::haveRight('internet', 'w')) {
         return false;
      }

      if (!empty($this->fields['itemtype']) && !empty($this->fields['items_id'])) {
         $item = new $this->fields['itemtype']();
         if ($item->getFromDB($this->fields['items_id'])) {
            return $item->canCreate();
         }
      }

      return true;
   }


   function canView() {

      if (!Session::haveRight('internet', 'r')) {
         return false;
      }

      if (!empty($this->fields['itemtype']) && !empty($this->fields['items_id'])) {
         $item = new $this->fields['itemtype']();
         if ($item->getFromDB($this->fields['items_id'])) {
            return $item->canView();
         }
      }

      return true;
   }


   static function getTypeName($nb=0) {
      return _n('Network name', 'Network names', $nb);
   }


   function defineTabs($options=array()) {

      $ong  = array();
      $this->addStandardTab('NetworkAlias', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }


   /**
    * Print the network name form
    *
    * @param $ID        integer ID of the item
    * @param $options   array
    *     - target for the Form
    *     - withtemplate template or basic computer
    *
    *@return Nothing (display)
   **/
   function showForm($ID, $options=array()) {
      global $CFG_GLPI;

      if (!Session::haveRight("internet", "r")) {
         return false;
      }

      if ($ID > 0) {
         $this->check($ID,'r');
      } else {
         $this->check(-1, 'w', $options);
      }

      $recursiveItems = $this->recursivelyGetItems();
      if (count($recursiveItems) == 0) {
         return false;
      }

      $lastItem = $recursiveItems[count($recursiveItems) - 1];

      $this->showTabs();

      $options['entities_id'] = $lastItem->getField('entities_id');
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'><td>";
      $this->displayRecursiveItems($recursiveItems, 'Type');
      echo "&nbsp;:</td>\n<td>";

      if (!($ID>0)) {
         echo "<input type='hidden' name='items_id' value='".$this->fields["items_id"]."'>\n";
         echo "<input type='hidden' name='itemtype' value='".$this->fields["itemtype"]."'>\n";
      }
      $this->displayRecursiveItems($recursiveItems, "Link");
      echo "</td>\n";
      echo "<td>" . __('Name') . "</td><td>\n";
      Html::autocompletionTextField($this, "name");
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";

      echo "<td>".IPNetwork::getTypeName(2)."&nbsp;";
      Html::showToolTip(__('IP network is not included in the database. However, you can see current available networks.'));
      echo "</td><td>";
      IPNetwork::showIPNetworkProperties($this->getEntityID());
      echo "</td>\n";

      echo "<td>".FQDN::getTypeName(1)."</td><td>";
      Dropdown::show(getItemTypeForTable(getTableNameForForeignKeyField("fqdns_id")),
                     array('value'       => $this->fields["fqdns_id"],
                           'name'        => 'fqdns_id',
                           'entity'      => $this->getEntityID(),
                           'displaywith' => array('view')));
      echo "</td>\n</tr>\n";

      echo "<tr class='tab_bg_1'>";
      $address = new IPAddress();
      echo "<td>".$address->getTypeName(2);
      $address->showAddButtonForChildItem($this, '_ipaddresses');
      echo "</td>";
      echo "<td>";
      $address->showFieldsForItemForm($this, '_ipaddresses', 'name');
      echo "</td>\n";

      echo "<td>".__('Comments')."</td>";
      echo "<td><textarea cols='45' rows='4' name='comment' >".$this->fields["comment"];
      echo "</textarea></td>\n";
      echo "</tr>";

      $this->showFormButtons($options);
      $this->addDivForTabs();

      return true;
   }


   function getSearchOptions() {

      $tab = parent::getSearchOptions();

      $tab[12]['table']         = 'glpi_fqdns';
      $tab[12]['field']         = 'fqdn';
      $tab[12]['name']          = FQDN::getTypeName(1);

      $tab[13]['table']         = 'glpi_ipaddresses';
      $tab[13]['field']         = 'name';
      $tab[13]['name']          = IPAddress::getTypeName(1);
      $tab[13]['joinparams']    = array('jointype' => 'itemtype_item');
      $tab[13]['forcegroupby']  = true;
      $tab[13]['massiveaction'] = false;

      $tab[20]['table']        = $this->getTable();
      $tab[20]['field']        = 'itemtype';
      $tab[20]['name']         = __('Type');
      $tab[20]['datatype']     = 'itemtype';
      $tab[20]['massiveation'] = false;

      $tab[21]['table']        = $this->getTable();
      $tab[21]['field']        = 'items_id';
      $tab[21]['name']         = __('id');
      $tab[21]['datatype']     = 'integer';
      $tab[21]['massiveation'] = false;

      return $tab;
   }


   /**
    * Check input validity for CommonDBTM::add and CommonDBTM::update
    *
    * @param $input the input given to CommonDBTM::add or CommonDBTM::update
    *
    * @return $input altered array of new values;
   **/
   function prepareInput($input) {

      if (isset($input["_ipaddresses"])) {
         $addresses = IPAddress::checkInputFromItem($input["_ipaddresses"],
                                                    self::getType(), $this->getID());

         if (count($addresses["invalid"]) > 0) {
            $msg = sprintf(_n('Invalid IP address: %s', 'Invalid IP addresses: %s',
                              count($addresses["invalid"])),
                           implode (', ',$addresses["invalid"]));
            Session::addMessageAfterRedirect($msg, false, ERROR);
            unset($addresses["invalid"]);
         }

         // TODO : is it usefull to check that there is at least one IP address ?
         // if ((count($addresses["new"]) + count($addresses["previous"])) == 0) {
         //    Session::addMessageAfterRedirect(__('No IP address (v4 or v6) defined'), false, ERROR);
         //    return false;
         // }

         $this->IPs             = $addresses;
         $input["_ipaddresses"] = "";
      }

      return $input;
   }


   function prepareInputForAdd($input) {

      $input = $this->prepareInput($input);

      if (!is_array($input)) {
         return false;
      }
      return parent::prepareInputForAdd($input);
   }


   function prepareInputForUpdate($input) {

      $input = $this->prepareInput($input);

      if (!is_array($input)) {
         return false;
      }
      return parent::prepareInputForUpdate($input);
   }


   function pre_deleteItem() {

      IPAddress::cleanAddress($this->getType(), $this->GetID());
      return parent::pre_deleteItem();
   }


   /**
    * \brief Update IPAddress database
    * Update IPAddress database to remove old IPs and add new ones. Update this "IPs" cache field
    * with the current IP addresses according to the database
    * And, if the addresses are different than before, recreate the link with the networks
   **/
   function post_workOnItem() {

      if (isset($this->IPs)) {
         global $DB;

         // Update IPAddress database : return value is a list of
         $newIPaddressField      = IPAddress::updateDatabase($this->IPs, $this->getType(),
                                                             $this->getID());

         $new_ip_addresses_field = implode('\n', $newIPaddressField);

         $query = "UPDATE `".$this->getTable()."`
                   SET `ip_addresses` = '$new_ip_addresses_field'
                   WHERE `id` = '".$this->getID()."'";
         $DB->query($query);

         unset($this->IPs);

      } else {
         $new_ip_addresses_field = "";
      }
   }


   function post_addItem() {

      $this->post_workOnItem();
      parent::post_addItem();
   }


   function post_updateItem($history=1) {

      $this->post_workOnItem();
      parent::post_updateItem($history);
   }


   function cleanDBonPurge() {

      $alias = new NetworkAlias();
      $alias->cleanDBonItemDelete($this->getType(), $this->GetID());

      $ipAddress = new IPAddress();
      $ipAddress->cleanDBonItemDelete($this->getType(), $this->GetID());
   }


   /**
    * \brief dettach an address from an item
    *
    * The address can be unaffected, and remain "free"
    *
    * @param $items_id  the id of the item
    * @param $itemtype  the type of the item
    *
   **/
   static function unaffectAddressesOfItem($items_id, $itemtype) {
      global $DB;

      $query = "SELECT `id`
                FROM `glpi_networknames`
                WHERE `items_id` = '".$items_id."'
                AND `itemtype` = '".$itemtype."'";

      foreach ($DB->request($query) as $networkNameID) {
         self::unaffectAddressByID($networkNameID['id']);
      }
   }


   /**
    * \brief dettach an address from an item
    *
    * The address can be unaffected, and remain "free"
    *
    * @param $networkNameID the id of the NetworkName
   **/
   static function unaffectAddressByID($networkNameID) {
      self::affectAddress($networkNameID, 0, '');
   }


   /**
    * @param $networkNameID
    * @param $items_id
    * @param $itemtype
   **/
   static function affectAddress($networkNameID, $items_id, $itemtype) {

      $networkName = new self();
      $networkName->update(array('id'       => $networkNameID,
                                 'items_id' => $items_id,
                                 'itemtype' => $itemtype));
   }


   /**
    * Get the full name (internet name) of a NetworkName
    *
    * @param $ID ID of the NetworkName
    *
    * @return its internet name, or empty string if invalid NetworkName
   **/
   static function getInternetNameFromID($ID) {

      $networkName = new self();

      if ($networkName->can($ID, 'r')) {
         return FQDNLabel::getInternetNameFromLabelAndDomainID($this->fields["name"],
                                                               $this->fields["fqdns_id"]);
      }
      return "";
   }


   /**
    * @param $networkPortID
   **/
   static function showFormForNetworkPort($networkPortID) {
      global $DB;

      $name         = new self();
      $number_names = 0;

      if ($networkPortID > 0) {
         $query = "SELECT `id`
                   FROM `".$name->getTable()."`
                   WHERE `itemtype` = 'NetworkPort'
                   AND `items_id` = '$networkPortID'";
         $result = $DB->query($query);

         if ($DB->numrows($result) > 1) {
            echo "<tr class='tab_bg_1'><th colspan='4'>" .
                   __("Several network names available! Go to the tab 'Network Name' to manage them.") .
                 "</th></tr>\n";
            return;
         }

        switch ($DB->numrows($result)) {
            case 1 :
               $nameID = $DB->fetch_assoc($result);
               $name->getFromDB($nameID['id']);
               break;

            case 0 :
               $name->getEmpty();
               break;
         }

      } else {
         $name->getEmpty();
      }

      echo "<tr class='tab_bg_1'><th colspan='4'>" . $name->getTypeName(1);
      if ($name->getID() > 0) {
         echo "<input type='hidden' name='NetworkName_id' value='".$name->getID()."'>\n";
      }
      echo "</th></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . self::getTypeName(1) . "</td><td>\n";
      Html::autocompletionTextField($name, "name", array('name' => 'NetworkName_name'));
      echo "</td>\n";

      $address = new IPAddress();
      echo "<td rowspan='2'>".$address->getTypeName(2);
      $address->showAddButtonForChildItem($name, 'NetworkName__ipaddresses');
      echo "</td>";
      echo "<td rowspan='2'>";
      $address->showFieldsForItemForm($name, 'NetworkName__ipaddresses', 'name');
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".FQDN::getTypeName(1)."</td><td>";
      Dropdown::show(getItemTypeForTable(getTableNameForForeignKeyField("fqdns_id")),
                     array('value'       => $name->fields["fqdns_id"],
                           'name'        => 'NetworkName_fqdns_id',
                           'entity'      => $name->getEntityID(),
                           'displaywith' => array('view')));
      echo "</td></tr>\n";
      echo "</tr>\n";
   }


   static function getHTMLTableHeaderForItem($itemtype, HTMLTable_Group $group,
                                              HTMLTable_SuperHeader $header,
                                              HTMLTable_Header $father = NULL,
                                              $options=array()) {

      $column_name = __CLASS__;

      if (isset($options['dont_display'][$column_name])) {
         return;
      }

      $content = self::getTypeName();
      if (isset($options['column_links'][$column_name])) {
         $content = "<a href='".$options['column_links'][$column_name]."'>$content</a>";
      }
      $this_header = $group->addHeader($header, $column_name, $content, $father);

      NetworkAlias::getHTMLTableHeaderForItem(__CLASS__, $group, $header, $this_header);
      IPAddress::getHTMLTableHeaderForItem(__CLASS__, $group, $header, $this_header);
   }


   static function getHTMLTableForItem(HTMLTable_Row $row, CommonDBTM $item = NULL,
                                        HTMLTable_Cell $father = NULL, array $options) {
      global $DB, $CFG_GLPI;

      $column_name = __CLASS__;
      if (isset($options['dont_display'][$column_name])) {
         return;
      }

      $header= $row->getGroup()->getHeader('Internet', __CLASS__);
      if (!$header) {
         return;
      }

      if (empty($item)) {
         if (empty($father)) {
            return;
         }
         $item = $father->getItem();
      }

      switch ($item->getType()) {
         case 'IPNetwork' :
            $query = "SELECT `glpi_networknames`.`id`
                      FROM `glpi_networknames`, `glpi_ipaddresses`, `glpi_ipaddresses_ipnetworks`
                      WHERE `glpi_networknames`.`id` = `glpi_ipaddresses`.`items_id`
                            AND `glpi_ipaddresses`.`itemtype` = 'NetworkName'
                            AND `glpi_ipaddresses`.`id` =`glpi_ipaddresses_ipnetworks`.`ipaddresses_id`
                            AND `glpi_ipaddresses_ipnetworks`.`ipnetworks_id` = '".$item->getID()."'";
            if (isset($options['order'])) {
               switch ($options['order']) {
                  case 'name' :
                     $query .= " ORDER BY `glpi_networknames`.`name`";
                     break;

                  case 'ip' :
                     $query .= " ORDER BY `glpi_ipaddresses`.`binary_3`,
                                          `glpi_ipaddresses`.`binary_2`,
                                          `glpi_ipaddresses`.`binary_1`,
                                          `glpi_ipaddresses`.`binary_0`";
                     break;
               }
            }
            break;

         case 'FQDN' :
            $query = "SELECT `glpi_networknames`.`id`
                      FROM `glpi_networknames`
                      WHERE `fqdns_id` = '".$item->fields["id"]."'
                      ORDER BY `glpi_networknames`.`name`";
            break;

         case 'NetworkEquipment' :
         case 'NetworkPort' :
            $query = "SELECT `id`
                      FROM `glpi_networknames`
                      WHERE `itemtype` = '".$item->getType()."'
                            AND `items_id` = '".$item->getID()."'";
            break;
      }

      if (isset($options['SQL_options'])) {
         $query .= " ".$options['SQL_options'];
      }

      $canedit              = ((isset($options['canedit']))   && ($options['canedit']));
      $createRow            = ((isset($options['createRow'])) && ($options['createRow']));
      $options['createRow'] = false;
      $address              = new self();

      foreach ($DB->request($query) as $line) {
         if ($address->getFromDB($line["id"])) {

            if ($createRow) {
               $row = $row->createAnotherRow();
            }

            $content      = "<a href='" . $address->getLinkURL(). "'>";
            $internetName = $address->getInternetName();
            if (empty($internetName)) {
               $content .= "(".$line["id"].")";
            } else {
               $content .= $internetName;
            }
            $content .= "</a>";

            if ($canedit) {
               $content .= "&nbsp;- <a href='" . $address->getFormURL() .
                           "?remove_address=unaffect&id=" . $address->getID() . "'>&nbsp;".
                           "<img src=\"" . $CFG_GLPI["root_doc"] .
                           "/pics/sub_dropdown.png\" alt=\"" . __s('Dissociate') .
                           "\" title=\"" . __s('Dissociate') . "\"></a>";
               $content .= "&nbsp;- <a href='" . $address->getFormURL() .
                           "?remove_address=purge&id=" . $address->getID() . "'>&nbsp;".
                           "<img src=\"" . $CFG_GLPI["root_doc"] .
                           "/pics/delete.png\" alt=\"" . __s('Purge') . "\" title=\"" .
                           __s('Purge') . "\"></a>";
            }

            $this_cell = $row->addCell($header, $content, $father, $address);

            NetworkAlias::getHTMLTableForItem($row, NULL, $this_cell, $options);
            IPAddress::getHTMLTableForItem($row, NULL, $this_cell, $options);

         }
      }
   }


   /**
    * \brief Show names for an item from its form
    * Beware that the rendering can be different if readden from direct item form (ie : add new
    * NetworkName, remove, ...) or if readden from item of the item (for instance from the computer
    * form through NetworkPort::ShowForItem).
    *
    * @param $item                     CommonGLPI object
    * @param $withtemplate   integer   withtemplate param (default 0)
   **/
   static function showForItem(CommonGLPI $item, $withtemplate=0) {
      global $DB, $CFG_GLPI;

      $table_options = array('createRow' => true);

      if (($item->getType() == 'IPNetwork') || ($item->getType() == 'FQDN')) {
         if (isset($_REQUEST["start"])) {
            $start = $_REQUEST["start"];
         } else {
            $start = 0;
         }

         if ($item->getType() == 'IPNetwork') {
            if (!empty($_REQUEST["order"])) {
               $table_options['order'] = $_REQUEST["order"];
            } else {
               $table_options['order'] = 'name';
            }

            $table_options['dont_display'] = array('IPNetwork' => true);
            $table_options['column_links'] =
                 array('NetworkName' => 'javascript:reloadTab("order=name");',
                      'IPAddress'   => 'javascript:reloadTab("order=ip");');

         }

         $table_options['SQL_options']  = "LIMIT ".$_SESSION['glpilist_limit']."
                                           OFFSET $start";

         $canedit = false;

      } else {
         $canedit = true;
      }

      $table_options['canedit']   = $canedit;

      $table  = new HTMLTable_();
      $column  = $table->addHeader('Internet', self::getTypeName(2));
      $t_group = $table->createGroup('Main', '');

      $address  = new self();

      self::getHTMLTableHeaderForItem(__CLASS__, $t_group, $column);

      $t_row   = $t_group->createRow();

      // Reorder the columns for better display
      switch ($item->getType()) {
         case 'NetworkEquipment' :
         case 'NetworkPort' :
         case 'FQDN' :
            break;

         case 'IPNetwork' :
            //$table->setColumnOrder(array('NetworkName', 'IPAddress', 'NetworkAlias'));
            break;
      }

      self::getHTMLTableForItem($t_row, $item, NULL, $table_options);

      if ($table->getNumberOfRows() > 0) {

         if (($item->getType() == 'IPNetwork') || ($item->getType() == 'FQDN')) {
            Html::printAjaxPager(self::getTypeName(2), $start, self::countForItem($item));
         }
         Session::initNavigateListItems(__CLASS__,
                                 //TRANS : %1$s is the itemtype name,
                                 //        %2$s is the name of the item (used for headings of a list)
                                        sprintf(__('%1$s = %2$s'),
                                                $item->getTypeName(1), $item->getName()));
         $table->display(array('display_title_for_each_group' => false,
                               'display_thead'                => false,
                               'display_tfoot'                => false));
      } else {
         echo "<table class='tab_cadre_fixe'><tr><th>".__('No network name found')."</th></tr>";
         echo "</table>";
      }

      if (($item->getType() == 'NetworkEquipment') || ($item->getType() == 'NetworkPort')) {

         $items_id = $item->getID();
         $itemtype = $item->getType();

         echo "<div class='center'>\n";
         echo "<table class='tab_cadre'>\n";

         echo "<tr><th>".__('Add a network name')."</th></tr>";

         echo "<tr><td class='center'>";
         echo "<a href=\"" . $address->getFormURL()."?items_id=$items_id&itemtype=$itemtype\">";
         echo __('New one')."</a>";
         echo "</td></tr>\n";

         echo "<tr><td class='center'>";
         echo "<form method='post' action='".$address->getFormURL()."'>\n";
         echo "<input type='hidden' name='items_id' value='$items_id'>\n";
         echo "<input type='hidden' name='itemtype' value='$itemtype'>\n";

         _e('Not associated one');
         echo "&nbsp;";
         Dropdown::show(__CLASS__, array('name'      => 'addressID',
                                         'condition' => '`items_id`=0'));
         echo "&nbsp;<input type='submit' name='assign_address' value='" . __s('Associate') .
                      "' class='submit'>";
         echo "</form>\n";
         echo "</td></tr>\n";

         echo "</table>\n";
         echo "</div>\n";
      }

   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      switch ($item->getType()) {
         case 'NetworkEquipment' :
         case 'NetworkPort' :
         case 'IPNetwork' :
         case 'FQDN' :
             self::showForItem($item, $withtemplate);
            break;
      }
   }


   static function countForItem(CommonDBTM $item) {
      global $DB;

      switch ($item->getType()) {
         case 'IPNetwork' :
            $query = "SELECT DISTINCT COUNT(*) AS cpt
                      FROM `glpi_ipaddresses`, `glpi_ipaddresses_ipnetworks`
                      WHERE `glpi_ipaddresses`.`itemtype` = 'NetworkName'
                            AND `glpi_ipaddresses`.`id` =`glpi_ipaddresses_ipnetworks`.`ipaddresses_id`
                            AND `glpi_ipaddresses_ipnetworks`.`ipnetworks_id` = '3'";
            $result = $DB->query($query);
            $ligne  = $DB->fetch_assoc($result);
            return $ligne['cpt'];

         case 'FQDN' :
            return countElementsInTable('glpi_networknames',
                                        "`fqdns_id` = '".$item->fields["id"]."'");

         case 'NetworkEquipment' :
         case 'NetworkPort' :
            return countElementsInTable('glpi_networknames',
                                        "itemtype = '".$item->getType()."'
                                             AND items_id = '".$item->getID()."'");
      }
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getID()
          && $item->can($item->getField('id'),'r')) {
         if ($_SESSION['glpishow_count_on_tabs']) {
            return self::createTabEntry(self::getTypeName(2), self::countForItem($item));
         }
         return self::getTypeName(2);
      }
      return '';
   }
}
?>