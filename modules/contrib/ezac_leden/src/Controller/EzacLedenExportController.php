<?php

namespace Drupal\ezac_leden\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ezac_leden\Model\EzacLid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Controller for EZAC leden administratie
 */
class EzacLedenExportController extends ControllerBase {

  /**
   * Maak exportbestand uit Leden tabel
   * geformatteerd voor input in bestand (csv)
   * Output via html headers naar attachment
   *
   * @param string $filename
   * @param null $code
   *
   * @return mixed Response output text in csv format
   *   output text in csv format
   */
  public function leden($filename = 'ezac.txt', $code = NULL, $actief = true) {

    if ($filename == '') {
      $filename = "ezac-$code.txt";
    }

    // Determine CODE categorie from Leden for export
    if (isset($code)) {
      $condition = [
        'code' => $code,
        'actief' => $actief,
      ];
    }
    else {
      $condition = ['actief' => $actief];
    } //select all active records

    $records = EzacLid::index($condition); //read records index

    $output = ""; // initialize output
    //build header line
    foreach (EzacLid::$fields as $field => $description) {
      $output .= '"' . $field . '";';
    }
    //remove last ";" 
    $output = rtrim($output, ";") . "\r\n";

    // export all records
    foreach ($records as $id) {
      $lid = new EzacLid($id);
      // add all fields
      foreach (EzacLid::$fields as $field => $description) {
        $output .= sprintf('"%s";', $lid->$field);
      }
      //remove last ";" 
      $output = rtrim($output, ";") . "\r\n";
    }
    return self::export($output, $filename);
  } // exportLedenCode

  public function etiketten($selectie, $sortering) {

    //Algemene query voor output file
    /*
    $query1 = 'SELECT Voornaam, Voorvoeg, Achternaam, Adres, Postcode, ';
    $query1 .= 'Plaats, Land, Telefoon, Code, E_mail, Geboorteda ';
    $query1 .= 'FROM {ezac_Leden} ';

    //Volgorde zoals aangevraagd
    switch ($sortering) {
      case "adres":
        $query3 = ' ORDER by Land, Postcode, Achternaam, Voornaam';
        break;
      case "naam":
        $query3 = ' ORDER by Achternaam, Land, Postcode, Voornaam';
        break;
    } //switch
    */

    /* subject */
    $subject = "EZAC etiketten - ";

    $condition['actief'] = TRUE;

    switch ($selectie) {
      case 'clubblad':
        //$query2  = 'WHERE Actief AND Etiketje';
        $condition['etiketje'] = TRUE;
        $subject .= 'Clubblad';
        break;
      case 'vergadering':
        //$query2  = "WHERE Actief AND (";
        //$query2 .= "CODE = 'VL' OR ";
        //$query2 .= "CODE = 'AL')";
        $condition['code'] = [
          'value' => ['AL', 'VL'],
          'operator' => 'IN',
        ];
        $subject .= "ledenvergadering";
        break;
      case 'receptie':
        //$query2  = "WHERE Actief AND (";
        //$query2 .= "CODE = 'AL' OR ";
        //$query2 .= "CODE = 'VL' OR ";
        //$query2 .= "CODE = 'AVL' OR ";
        //$query2 .= "CODE = 'DO' OR ";
        //$query2 .= "CODE = 'DB')";
        $condition['code'] = [
          'value' => ['AL', 'VL', 'AVL', 'DO', 'DB'],
          'operator' => 'IN',
        ];
        $subject .= "receptie";
        break;
      case 'alles':
        //$query2  = "Where Actief AND CODE <> 'BF'";
        $condition['code'] = [
          'value' => 'BF',
          'operator' => '<>',
        ];
        $subject .= "alle leden";
        break;
      case 'baby':
        //$query2  = "WHERE Actief AND Babyvriend";
        $condition['babyvriend'] = TRUE;
        $subject .= "Vrienden van Nico Baby";
        break;
      case 'VL':
        //$query2  = "WHERE Actief AND ";
        //$query2 .= "CODE = 'VL' OR ";
        //$query2 .= "CODE = 'AVL'";
        $condition['code'] = [
          'value' => ['AVL', 'VL'],
          'operator' => 'IN',
        ];
        $subject .= "Vliegende Leden";
        break;
      case 'camping':
        //$query2  = "WHERE Actief AND Camping";
        $condition['camping'] = TRUE;
        $subject .= "Camping gebruikers";
        break;
      default:
        $subject .= "geen selectie herkend";
    } //switch

    // data header for CSV attachment
    $mess = ('"Naam";"Adres";"Postcode";"Plaats";"Land";"Telefoon";"E-mail";"Code";"Geboorteda"' . "\r\n");
    $mess .= ('"EZAC leden";"' . $selectie . '";"";"' . date("Y-m-d") . '";"";"";"";"";""' . "\r\n");

    //execute query
    //echo $query1 .$query2 .$query3;
    //$result = db_query ($query1 .$query2 .$query3)
    //or die ("Query failed :" .$query1 .$query2 .$query3);

    // $sortering = adres | achternaam
    $sort = ($sortering == 'achternaam') ? 'achternaam' : 'adres';
    $ledenIndex = EzacLid::index($condition, 'id', $sort);

    //output result
    foreach ($ledenIndex as $id) {
      $lid = new EzacLid($id);

      $Naam = "";
      if (isset($lid->voornaam)) {
        $Naam .= $lid->voornaam;
      }
      if (isset($lid->voorvoeg)) {
        if ($Naam <> "") {
          $Naam .= " ";
        }
        $Naam .= $lid->voorvoeg;
      }
      if (isset($lid->achternaam)) {
        if ($Naam <> "") {
          $Naam .= " ";
        }
        $Naam .= $lid->achternaam;
      }
      $mess .= ('"' . $Naam . '";');
      $mess .= ('"' . $lid->adres . '";');
      $mess .= ('"' . $lid->postcode . '";"' . $lid->plaats . '";');
      $mess = isset($lid->land) ? $mess . ('"' . $lid->land . '";') : $mess . ('"";');
      $mess = isset($lid->telefoon) ? $mess . ('"' . $lid->telefoon . '";') : $mess . ('"";');
      $mess = isset($lid->e_mail) ? $mess . ('"' . $lid->e_mail . '";') : $mess . ('"";');
      $mess = isset($lid->code) ? $mess . ('"' . $lid->code . '";') : $mess . ('"";');
      $mess = isset($lid->geboorteda) ? $mess . ('"' . $lid->geboorteda . '"') : $mess . ('""');
      $mess .= "\r\n";
    } //while
    return self::export($mess, $subject);
  }

  /**
   * helper functie - zet datum om van systeemdatum JJJJ-MM-DD naar DD-MM-JJJJ
   *
   * @param $datum
   *
   * @return mixed|string
   */
  function switchdate($datum): ?string {
    if ($datum != NULL) {
      $lv = explode('-', $datum);
      $datum = sprintf('%s-%s-%s', $lv[2], $lv[1], $lv[0]);
    }
    return $datum;
  }//switchdate

  public function davilex($filename = 'davilex.txt') {
    $condition = [
      'actief' => TRUE,
      'afkorting' => [
        'value' => '',
        'operator' => '<>',
      ],
    ];
    $ledenIndex = EzacLid::index($condition, 'id', 'achternaam');
    /*
    $query  = 'SELECT afkorting, ';
    $query .= "CONCAT_WS(' ',voornaam,voorvoeg,achternaam) AS naam, ";
    $query .= 'voornaam, voorvoeg, voorletter, achternaam, ';
    $query .= 'adres, postcode, plaats, ';
    $query .= 'telefoon, land, e_mail, code, afkorting, geboorteda AS geboortedatum, ';
    $query .= 'lid_van, lid_eind ';
    $query .= 'FROM {ezac_Leden} WHERE actief ';
    $query .= "AND afkorting <> '' ";
    $query .= 'ORDER by achternaam, postcode, code';

    $result = db_query ($query) ;
    */

    $data = ""; //bestand export file data
    $data .= '"ZoekCode";"Naam";"Voornaam";"Tussenvoegsel";"Achternaam";"Voorletters";"Geboortedatum";';
    $data .= '"Straat hoofdadres";"Postcode hoofdadres";"Plaats hoofdadres";"Land hoofdadres";';
    $data .= '"Straat postadres";"Postcode postadres";"Plaats postadres";"Provincie postadres";"Land postadres";';
    $data .= '"E-mailadres";"Telefoonnummer";';
    $data .= '"Begindatum lidmaatschap";"Einddatum lidmaatschap"' ."\r\n";

    /* Velden in Davilex exportbestand:
    * "Zoekcode";
    * "Naam"; "Voornaam"; "Tussenvoegsel"; "Achternaam";"Voorletters";
    * "Geboortedatum";
    "Titel";
    "Geslacht";
    "Functie";
    "Bank-/girorekening";
    "Betalingswijze";"
    Relatiebeheerder";
    "Niet actief";
    * "Straat hoofdadres";"Postcode hoofdadres";"Plaats hoofdadres";"Provincie hoofdadres";"Land hoofdadres";
    * "E-mailadres";
    "Faxnummer";
    * "Telefoonnummer";
    "Webpagina";
    >> "Straat postadres";"Postcode postadres";"Plaats postadres";"Provincie postadres";"Land postadres";
    "Factuur toesturen";
    * "Begindatum lidmaatschap";
    * "Einddatum lidmaatschap";
    "Postadres via ander lid";
    "Betalend lid";
    "KvK-nummer";
    "Soort relatie";
    "Persoontype";
    "Naam contactpersoon";
    "Afdeling";
    "Is afzonderlijke relatie";
    "BTW-nummer";
    "BrancheZoekcode";
    "BrancheBranchenaam";
    "RechtsvormAfkorting";
    "RechtsvormRechtsvorm";
    "Bedrijfsgrootte";
    "Valuta voor relatie";
    "Debiteur";
    "Crediteur";
    "Verkoopdagboek";"Verkooppostnummer";"Verkoper bij debiteur";"Kortingsmarge debiteur";"Prijslijst bij debiteur";"Kredietlimiet debiteur";"Debiteur geblokkeerd";"Betalingsvoorwaarde debiteur";"Leveringsvoorwaarde debiteur";
    "Inkoopdagboeknummer";"Inkooppostnummer";"Kortingsmarge crediteur";"Kredietlimiet crediteur";"Betalingsvoorwaarde crediteur";"Leveringsvoorwaarde crediteur";
    "Straat afleveradres";"Postcode afleveradres";"Plaats afleveradres";"Provincie afleveradres";"Land afleveradres";
    >> "Straat factuuradres";"Postcode factuuradres";"Plaats factuuradres";"Provincie factuuradres";"Land factuuradres"
    */

    foreach ($ledenIndex as $id) {
      $l = new EzacLid($id);
      $naam = "";
      if ($l->voornaam != '') $naam = $l->voornaam;
      if ($l->voorvoeg != '') $naam .= " $l->voorvoeg";
      if ($l->achternaam != '') $naam .= " $l->achternaam";
      $data .= sprintf('"%s";"%s";"%s";"%s";"%s";', $l->afkorting, $naam, $l->voornaam, $l->voorvoeg, $l->achternaam);
      $data .= sprintf('"%s";"%s";"%s";"%s";"%s";"%s";', $l->voorletter, $l->geboorteda, $l->adres, $l->postcode, $l->plaats, $l->land);
      $data .= sprintf('"%s";"%s";"%s";"%s";"%s";', $l->adres, $l->postcode, $l->plaats, '', $l->land);
      //postadres gelijk aan hoofdadres doorgeven
      $data .= sprintf('"%s";"%s";', $l->e_mail, $l->telefoon);
      $data .= sprintf('"%s";"%s"', self::switchdate($l->lid_van), self::switchdate($l->lid_eind)) . "\r\n";
    } //while
    return self::export($data, $filename);
  }

  function export(string $data, $filename) {
    $response = new Response(
      $data,
      Response::HTTP_OK,
      [
        'content-type' => 'text/plain',
      ]
    );

    $disposition = $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );
    $response->headers->set('Content-Disposition', $disposition);
    $response->setCharset('UTF-8');

    /** @var mixed $response */
    return $response;
  } // export  

} //class EzacLedenExportController
