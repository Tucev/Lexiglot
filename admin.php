<?php
// +-----------------------------------------------------------------------+
// | Lexiglot - A PHP based translation tool                               |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2011-2013 Damien Sorel       http://www.strangeplanet.fr |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

define('LEXIGLOT_PATH', './');
define('IN_ADMIN', 1);
include(LEXIGLOT_PATH . 'include/common.inc.php');
include(LEXIGLOT_PATH . 'admin/include/functions.inc.php');

// check rights
if ( !is_manager() and !is_admin() )
{
  array_push($page['errors'], 'Your are not allowed to view this page. <a href="user.php?login">Login</a>.');
  $template->close('messages');
}

if (is_manager())
{
  array_push($page['infos'], 'As a project(s) manager you can only view information relative to your project(s).');
}

// +-----------------------------------------------------------------------+
// |                         LOCATION
// +-----------------------------------------------------------------------+
// admin pages
if (is_admin())
{
  $pages = array(
    'history' => 'History', 
    'commit' => 'Commit',
    'users' => 'Users',
    'projects' => 'Projects',
    'languages' => 'Languages', 
    'mail' => 'Mail archive',
    'config' => 'Configuration',
    'maintenance' => 'Maintenance',
    );
  $sub_pages = array(
    'user_perm' => 'User permissions',
    );
}
// manager pages
else if (is_manager())
{
  $pages = array(
    'history' => 'History', 
    'commit' => 'Commit',
    'projects' => 'Projects',
    );
    
  if ($user['manage_perms']['can_change_users_projects'])
  {
    $pages['users'] = 'Users';
    $sub_pages = array(
      'user_perm' => 'User permissions',
      );
  }
}
    

if ( isset($_GET['page']) and array_key_exists($_GET['page'], array_merge($pages, $sub_pages)) )
{
  $page['page'] = $_GET['page'];
}
else
{
  $page['page'] = 'history';
}


// +-----------------------------------------------------------------------+
// |                         PAGE OPTIONS
// +-----------------------------------------------------------------------+
// page title
$template->assign(array(
  'WINDOW_TITLE' => 'Admin',
  'PAGE_TITLE' => 'Admin',
  ));

// tabsheet
include_once(LEXIGLOT_PATH . 'include/tabsheet.inc.php');
$tabsheet = new Tabsheet('ADMIN', 'page');
foreach ($pages as $file => $name)
{
  $tabsheet->add($file, $name, null, true);
}
if ( !array_key_exists($page['page'], $pages) )
{
  $tabsheet->add($page['page'], $sub_pages[ $page['page'] ], null, array());
}
$tabsheet->select($page['page']);
$tabsheet->render(false);


// +-----------------------------------------------------------------------+
// |                         MAIN
// +-----------------------------------------------------------------------+
include(LEXIGLOT_PATH . 'admin/'.$page['page'].'.php');

?>