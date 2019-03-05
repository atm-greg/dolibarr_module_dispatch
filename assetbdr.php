<?php
/**
 * Created by PhpStorm.
 * User: greg
 * Date: 04/03/19
 * Time: 15:27
 */

require 'config.php';
dol_include_once('/bonderetour/class/bonderetour.class.php');
dol_include_once('/bonderetour/lib/bonderetour.lib.php');
dol_include_once('/dispatch/class/dispatchasset.class.php');
dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');

$id = GETPOST('id', 'int');

$PDOdb = new TPDOdb;

$object = new Bonderetour($db);
$object->fetch($id);

$dispatch=new TDispatch;
$dispatch->loadByObject($PDOdb,$id,'bonderetour');

$action = GETPOST('action');

switch ($action) {
	case 'save':

		$TLine = GETPOST('TLine');
		if(!empty($TLine[-1]['serial_number']) && (!empty($TLine[-1]['fk_object']) || GETPOST('type_object') === 'ticketsup'))
			// Si type_object == ticketsup on n'empêche pas l'ajout si aucune ligne est sélectionnée car aucun sens d'associer un asset à un message sur un ticket
		{

			$asset = new TAsset;
			$asset->loadReference($PDOdb, $TLine[-1]['serial_number']);

			if($asset->getId()>0) {
				$k=$dispatch->addChild($PDOdb, 'TDispatchAsset');
				$dispatch->TDispatchAsset[$k]->fk_asset = $asset->getId();
				$dispatch->TDispatchAsset[$k]->fk_object = $TLine[-1]['fk_object'];
				$dispatch->TDispatchAsset[$k]->type_object = $type_object;
				$dispatch->TDispatchAsset[$k]->asset = $asset;

				$dispatch->save($PDOdb);
			}

		}

		break;
	case 'delete-line':
		$k = (int)GETPOST('k');
		$dispatch->TDispatchAsset[$k]->to_delete=true;

		$dispatch->save($PDOdb);
		break;
}

llxHeader();

$head = bonderetour_prepare_head($object);
dol_fiche_head($head, 'dispatchAsset', $langs->trans("Bonderetour"), 0);

if ($object->origin == 'commande')
{
    $object->fetch_origin();
    $object->commande->fetchObjectLinked($object->commande->id, 'commande');

    $TImport = array();
    if (!empty($object->commande->linkedObjects['shipping']))
    {
        foreach ($object->commande->linkedObjects['shipping'] as $k => $expe)
        {
            $TImport = array_merge($TImport, _loadDetail($PDOdb, $expe));
        }
    }

    if (!empty($TImport))
    {
        $Tserials = array();

        foreach ($TImport as $k => $line_asset)
        {

        }
        ?>
        <table width="100%" class="border">
        <tr class="liste_titre">
            <?php
            if(GETPOST('type_object') !== 'ticketsup') print '<td>Ligne concernée</td>';
            ?>
            <td>Equipement</td>
            <?php
            if(!empty($conf->global->USE_LOT_IN_OF)) {
                ?>
                <td>Numéro de Lot</td>
                <td>DLUO</td>
                <?php
            }
            ?>
            <?php
            if($conf->global->clinomadic->enabled){
                ?>
                <td>IMEI</td>
                <td>Firmware</td>
                <?php
            }
            ?>
            <td>&nbsp;</td>
        </tr>

        <?php
    }
}

print count($dispatch->TDispatchAsset).' équipement(s) lié(s)<br /><br />';

var_dump($TImport);

llxFooter();

function _loadDetail(&$PDOdb, &$expedition){

    $TImport = array();

    foreach($expedition->lines as $line){

        $sql = "SELECT ea.rowid as fk_expeditiondet_asset, a.rowid as id,a.serial_number,p.ref,p.rowid, ea.fk_expeditiondet, ea.lot_number, ea.weight_reel, ea.weight_reel_unit, ea.is_prepared
					FROM ".MAIN_DB_PREFIX."expeditiondet_asset as ea
						LEFT JOIN ".MAIN_DB_PREFIX.ATM_ASSET_NAME." as a ON ( a.rowid = ea.fk_asset)
						LEFT JOIN ".MAIN_DB_PREFIX."product as p ON (p.rowid = a.fk_product)
					WHERE ea.fk_expeditiondet = ".$line->line_id."
						ORDER BY ea.rang ASC";

        $PDOdb->Execute($sql);
        $Tres = $PDOdb->Get_All();

        foreach ($Tres as $res) {

            $TImport[] =array(
                'fk_expeditiondet_asset'=>$res->fk_expeditiondet_asset
            ,'ref'=>$res->ref
            ,'numserie'=>$res->serial_number
            ,'fk_product'=>$res->rowid
            ,'fk_expeditiondet'=>$res->fk_expeditiondet
            ,'lot_number'=>$res->lot_number
            ,'quantity'=>$res->weight_reel
            ,'quantity_unit'=>$res->weight_reel_unit
            ,'is_prepared'=>$res->is_prepared
            );
        }
    }

    return $TImport;
}
