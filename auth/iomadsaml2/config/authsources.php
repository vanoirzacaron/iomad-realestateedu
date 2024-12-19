<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * SSP auth sources which inherits from Moodle config
 *
 * @package    auth_iomadsaml2
 * @copyright  Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use auth_iomadsaml2\ssl_signing_algorithm;

defined('MOODLE_INTERNAL') || die();

global $iomadsam2auth, $CFG, $SITE, $SESSION;

$config = [];

$baseurl = optional_param('baseurl', $CFG->wwwroot, PARAM_URL);

if (!empty($SESSION->iomadsaml2idp) && array_key_exists($SESSION->iomadsaml2idp, $iomadsam2auth->metadataentities)) {
    $idpentityid = $iomadsam2auth->metadataentities[$SESSION->iomadsaml2idp]->entityid;
} else {
    // Case for specifying no $SESSION IdP, select the first configured IdP as the default.
    $idpentityid = reset($iomadsam2auth->metadataentities)->entityid;
}

$defaultspentityid = "$baseurl/auth/iomadsaml2/sp/metadata.php";

// Process requested attributes.
$attributes = [];
$attributesrequired = [];

foreach (explode(PHP_EOL, $iomadsam2auth->config->requestedattributes) as $attr) {
    $attr = trim($attr);
    if (empty($attr)) {
        continue;
    }
    if (substr($attr, -2, 2) === ' *') {
        $attr = substr($attr, 0, -2);
        $attributesrequired[] = $attr;
    }
    $attributes[] = $attr;
}
// Moodle language code does not always map to the iso code, which is preferable for xml:lang attributes.
$lang = get_string('iso6391', 'core_langconfig');

// IOMAD
require_once($CFG->dirroot . '/local/iomad/lib/company.php');
$companyid = iomad::get_my_companyid(context_system::instance(), false);
if (!empty($companyid)) {
    $postfix = "_$companyid";
} else {
    $postfix = "";
}

$config[$iomadsam2auth->spname] = [
    'saml:SP',
    'entityID' => !empty($iomadsam2auth->config->spentityid) ? $iomadsam2auth->config->spentityid : $defaultspentityid,
    'discoURL' => !empty($CFG->auth_iomadsaml2_disco_url) ? $CFG->auth_iomadsaml2_disco_url : null,
    'idp' => empty($CFG->auth_iomadsaml2_disco_url) ? $idpentityid : null,
    'NameIDPolicy' => ['Format' => $iomadsam2auth->config->nameidpolicy, 'AllowCreate' => true],
    'OrganizationName' => array(
        $lang => $SITE->shortname,
    ),
    'OrganizationDisplayName' => array(
        $lang => $SITE->fullname,
    ),
    'OrganizationURL' => array(
        $lang => $baseurl,
    ),
    'privatekey' => $iomadsam2auth->spname . '.pem',
    'privatekey_pass' => get_config('auth_iomadsaml2', 'privatekeypass' . $postfix),
    'certificate' => $iomadsam2auth->spname . '.crt',
    'sign.logout' => true,
    'redirect.sign' => true,
    'signature.algorithm' => $iomadsam2auth->config->signaturealgorithm,
    'WantAssertionsSigned' => $iomadsam2auth->config->wantassertionssigned == 1,

    'name' => [
        $lang => $SITE->fullname,
    ],
    'attributes' => $attributes,
    'attributes.required' => $attributesrequired,
];

if (!empty($iomadsam2auth->config->assertionsconsumerservices)) {
    $config[$iomadsam2auth->spname]['acs.Bindings'] = explode(',', $iomadsam2auth->config->assertionsconsumerservices);
}

if (!empty($iomadsam2auth->config->authncontext)) {
    $config[$iomadsam2auth->spname]['AuthnContextClassRef'] = $iomadsam2auth->config->authncontext;
}

/*
 * If we're configured to expose the nameid as an attribute, set this authproc filter up
 * the nameid value appears under the attribute "nameid"
 */
if ($iomadsam2auth->config->nameidasattrib) {
    $config[$iomadsam2auth->spname]['authproc'] = array(
        20 => array(
            'class' => 'saml:NameIDAttribute',
            'format' => '%V',
        ),
    );
}
