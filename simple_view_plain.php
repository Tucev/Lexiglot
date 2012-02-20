<?php
// +-----------------------------------------------------------------------+
// | Lexiglot - A PHP based translation tool                               |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2011-2012 Damien Sorel       http://www.strangeplanet.fr |
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

define('PATH', './');
include(PATH.'include/common.inc.php');

$page['header'].= '
<link type="text/css" rel="stylesheet" media="screen" href="template/public.css">
<style type="text/css">#the_page { margin-top:20px; width:580px; }</style>';

// +-----------------------------------------------------------------------+
// |                         PAGE OPTIONS
// +-----------------------------------------------------------------------+
// language
if ( !isset($_GET['language']) or !array_key_exists($_GET['language'], $conf['all_languages']) )
{
  array_push($page['errors'], 'Undefined or unknown language.');
  echo '<a href="'.get_url_string(array('section'=>$_GET['section']), 'all', 'section').'">Go Back</a>';
  print_page();
}
// section
if ( !isset($_GET['section']) or !array_key_exists($_GET['section'], $conf['all_sections']) )
{
  array_push($page['errors'], 'Undefined or unknown section.');
  echo '<a href="'.get_url_string(array('language'=>$_GET['language']), 'all', 'language').'">Go Back</a>';
  print_page();
}
// display
if ( isset($_GET['display']) and in_array($_GET['display'], array('plain','normal')) )
{
  $page['display'] = $_GET['display'];
}
else
{
  $page['display'] = 'plain';
}

$page['language'] = $_GET['language'];
$page['section'] = $_GET['section'];
$page['directory'] = $conf['local_dir'].$page['section'].'/';
$page['files'] = explode(',', $conf['all_sections'][$_GET['section']]['files']);

// file
if ( !isset($_GET['file']) or !in_array($_GET['file'], $page['files']) )
{
  array_push($page['errors'], 'Undefined or unknown file.');
  echo '<a href="javascript:window.close();">Close</a>';
  print_page(false);
}

$page['file'] = $_GET['file'];

// +-----------------------------------------------------------------------+
// |                         GET FILE
// +-----------------------------------------------------------------------+
$_LANG = load_language_file_plain($page['directory'].$page['language'].'/'.$page['file']);


// +-----------------------------------------------------------------------+
// |                         DISPLAY FILE
// +-----------------------------------------------------------------------+  
$page['caption'].= '
<a class="floating_link" href="javascript:window.close();">Close this window</a> <span class="floating_link">&nbsp;|&nbsp;</span>
'.get_section_name($page['section']).' &raquo; '.get_language_flag($page['language']).' '.get_language_name($page['language']);

if ($page['display'] == 'plain')
{
  $page['caption'].= '
  <a class="floating_link" href="'.get_url_string(array('display'=>'normal')).'">View normal</a>';
}
else
{
  $page['caption'].= '
  <a class="floating_link" href="'.get_url_string(array('display'=>'plain')).'">View plain</a>';
}

echo '
<form id="diffs">
<fieldset class="common">
  <legend>File content</legend>';
  if ($page['display'] == 'plain')
  {
    echo '
    <script type="text/javascript">$(document).ready(function(){$("pre").css("height", $(window).height()-130);});</script>
    <pre style="white-space:pre-wrap;overflow-y:scroll;">'.htmlspecialchars($_LANG['row_value']).'</pre>';
  }
  else
  {
    echo '
    <script type="text/javascript">$(document).ready(function(){$("iframe").css("height", $(window).height()-130);});</script>
    <iframe src="'.$page['directory'].$conf['default_language'].'/'.$page['file'].'" style="width:100%;margin:0;"></iframe>';
  }
echo '
</fieldset>
</form>';

print_page(false);
?>