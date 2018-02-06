## CSV Enrolls & Groups local plugin
#### Description ###
The CSV Enrolls & Groups local plugin allows you to import user enrollments for a course from a delimited text file. Fields can be delimited using commas, tabs, or semicolons.

Enrollments are made with the manual enrol plugin and using a selectable role. The plugin can optionally create course groups and assign the new enrollees to those groups.

Each of the users listed in the input file must have an existing Moodle user account; new Moodle **user accounts will not** be created.

This plugin was originally (Moodle 1.9) a refactor of the mass_enroll course admin mod done by Patrick Pollet and Valery Fremaux, using the standard groups course import plugin as a template. The current Moodle 2.x revision is again a refactor, but as a local plugin (placed into the Moodle {$CFG->dirroot}/local directory), and accessed from the course administration menu under the _Users_ item.

Besides being able to create groups, you also can select from existing groups and override the data file's group designation.

#### INSTALLATION
Place the userenrols directory in the Moodle site's local directory. Access the notifications admin page to confirm installation. When viewing a course, the instructor will be able to access the plugin from the Course administration menu block under the _Users_ item.

#### DISCLAIMER AND LICENSING
This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
