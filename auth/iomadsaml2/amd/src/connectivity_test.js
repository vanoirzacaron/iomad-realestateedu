
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
 * Module to check connectivity to IdP endpoint from client.
 *
 * @module     auth_iomadsaml2/connectivity_test
 * @copyright  2023 Peter Burnett <peterburnett@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = (checkTarget, redirURL) => {
    // We need to use no-cors to ignore cors, however,
    // this means we are returned an opaque response.
    // But an opaque response is info to say if the site is accessible or not.
    fetch(checkTarget, { mode: 'no-cors', method: 'HEAD' })
    .then(() => {
        window.location = redirURL;
    })
    // Do nothing with error, we don't care about it.
    .catch(() => {});
};
