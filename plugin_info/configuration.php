<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<form class="form-horizontal">
    <fieldset>
        <legend><i class="fas fa-user-cog"></i> {{Authentification}}</legend>
        <div class="form-group">
            <label class="col-sm-4 control-label">{{Nom d'utilisateur}}</label>
            <div class="col-sm-4">
                <input type="text" class="configKey form-control" data-l1key="user" placeholder="{{Saisir le nom d'utilisateur}}" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-4 control-label">{{Mot de passe}}</label>
            <div class="col-sm-4">
                <input type="password" class="configKey form-control" data-l1key="password" placeholder="{{Saisir le mot de passe}}" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-4 control-label">{{S-PIN}}</label>
            <div class="col-sm-4">
                <input type="password" class="configKey form-control" data-l1key="spin" placeholder="{{Saisir le S-PIN}}" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-4 control-label">{{Pays}}</label>
            <div class="col-sm-4">
                <select class="configKey form-control" data-l1key="country">
                    <option value="" disabled="">{{Sélectionner votre pays}}</option>
                    <option value="BE">{{Belgique}}</option>
                    <option value="DE">{{Allemagne}}</option>
                    <option value="ES">{{Espagne}}</option>
                    <option value="FR">{{France}}</option>
                    <option value="IT">{{Italie}}</option>
                    <option value="LU">{{Luxembourg}}</option>
                    <option value="NL">{{Pays-Bas}}</option>
                    <option value="PT">{{Portugal}}</option>
                    <option value="CH">{{Suisse}}</option>
                    <option value="GB">{{Royaume-Uni}}</option>
                    <option value="AT">{{Autriche}}</option>
                </select>
            </div>
        </div>
        <legend><i class="fas fa-university"></i> {{Options}}</legend>
        <div class="form-group">
            <label class="col-sm-4 control-label">{{Clé API Google Maps}}</label>
            <div class="col-sm-4">
                <input class="configKey form-control" data-l1key="googleMapsAPIKey" />
            </div>
        </div>
        <legend><i class="fas fa-university"></i> {{Démon}}</legend>
        <div class="form-group">
            <label class="col-sm-4 control-label">{{Port socket interne}}</label>
            <div class="col-sm-1">
                <input class="configKey form-control" data-l1key="socketport" placeholder="55066" />
            </div>
        </div>
    </fieldset>
</form>