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

defined('LEXIGLOT_PATH') or die('Hacking attempt!');

// +-----------------------------------------------------------------------+
// |                        SEND COMMIT
// +-----------------------------------------------------------------------+

// FOREACH COMMIT
foreach ($_ROWS as $props => $files)
{
  // commit infos
  list($commit['project'], $commit['language']) = explode('||', $props);
  $commit['path'] = $conf['local_dir'].$commit['project'].'/'.$commit['language'].'/';
  $commit['is_new'] = dir_is_empty($commit['path']);
  $commit['users'] = $commit['done_rows'] = $commit['errors'] = array();
  
  // FOREACH FILE
  foreach ($files as $filename => $file_content)
  {
    // file infos
    $file_infos['name'] = $filename;
    $file_infos['path'] = $commit['path'].$file_infos['name'];
    $file_infos['is_new'] = !file_exists($file_infos['path']);
    $file_infos['users'] = $file_infos['done_rows'] = $file_infos['errors'] = array();
    
    ## plain file ##
    if (is_plain_file($file_infos['name']))
    {
      $row = $file_content[ $file_infos['name'] ];
      
      // try to put the content in the file
      if (deep_file_put_contents($file_infos['path'], $row[0]['row_value']))
      {
        array_merge_ref($file_infos['done_rows'], array_unique_deep($row, 'id'));
        array_merge_ref($file_infos['users'], array_unique_deep($row, 'user_id'));
      }
      else
      {
        array_push($file_infos['errors'], 'Can\'t update/create file \''.$file_infos['path'].'\'');
      }
    }
    ## array file ##
    else
    {
      // load language files
      $_LANG =         load_language_file($commit['project'], $commit['language'], $file_infos['name']);
      $_LANG_default = load_language_file($commit['project'], $conf['default_language'], $file_infos['name']);
      
      // update the file
      if (!$file_infos['is_new'])
      {
        $_FILE = file($file_infos['path'], FILE_IGNORE_NEW_LINES);
        unset($_FILE[ array_search('?>', $_FILE) ]); // remove PHP end tag
      }
      // create the file
      else
      {
        $_FILE = array('<?php', $conf['new_file_content']);
      }
      
      // FOREACH ROW
      // rows from database (new/edit) we skip/remove obsolete
      foreach ($file_content as $key => $row)
      {
        $sub_string = is_sub_string($key);
        
        /* we search for
          $lang['a key']
          $lang["a key"]
          $lang[a key]
        */
        /*if ($sub_string !== false)
        {
          $sub = $sub_string;
          $search = '#\$'.$conf['var_name'].'\[(\''.str_replace("'","\\'",$sub[0]).'\'|"'.str_replace('"','\\"',$sub[0]).'"|'.$sub[0].')\]\[(\''.str_replace("'","\\'",$sub[1]).'\'|"'.str_replace('"','\\"',$sub[1]).'"|'.$sub[1].')\]#';
        }
        else
        {
          $search = '#\$'.$conf['var_name'].'\[(\''.str_replace("'","\\'",$key).'\'|"'.str_replace('"','\\"',$key).'"|'.$key.')\]#';

        }*/
        
        // supposing how the file line should looks like
        switch ($conf['quote'])
        {
          case "'":
            if ($sub_string !== false)
              $row[0]['search'] = "$".$conf['var_name']."['".str_replace("'","\'",$sub_string[0])."']['".str_replace("'","\'",$sub_string[1])."']";
            else
              $row[0]['search'] = "$".$conf['var_name']."['".str_replace("'","\'",$key)."']";
            $row[0]['content'] = $row[0]['search']." = '".str_replace("'","\'",$row[0]['row_value'])."';";
            break;
          case '"':
            if ($sub_string !== false)
              $row[0]['search'] = '$'.$conf['var_name'].'["'.str_replace('"','\"',$sub_string[0]).'"]["'.str_replace('"','\"',$sub_string[1]).'"]';
            else
              $row[0]['search'] = '$'.$conf['var_name'].'["'.str_replace('"','\"',$key).'"]';
            $row[0]['content'] = $row[0]['search'].' = "'.str_replace('"','\"',$row[0]['row_value']).'";';
            break;
        }
        
        // remove obsolete row, and continue to the next
        if (!isset($_LANG_default[$key]))
        {
          /*if ( 
            !$file_infos['is_new'] and
            isset($_POST['delete_obsolete']) and
            ($i = array_pos($row['search'], $_FILE)) !== false
          )
          {
            // if the end of the line is not the end of the row, we search the end into lines bellow
            if ( !preg_match('#(\'|");(\s*)$#', $_FILE[$i]) )
            {
              unset_to_eor($_FILE, $i);
            }
            unset($_FILE[$i]);
          }*/
          continue;
        }
        
        // update existing line
        if (
          !$file_infos['is_new'] and 
          ($i = array_pos($row[0]['search'], $_FILE)) !== false
        )
        {
          // if the end of the line is not the end of the row, we search the end into lines bellow
          if ( !preg_match('#(\'|");(\s*)$#', $_FILE[$i]) )
          {
            unset_to_eor($_FILE, $i);
          }
          $_FILE[$i] = $row[0]['content'];
        }
        // add new line at the end
        else
        {
          $_FILE[] = $row[0]['content'];
        }
        
        array_merge_ref($file_infos['done_rows'], array_unique_deep($row, 'id'));
        array_merge_ref($file_infos['users'], array_unique_deep($row, 'user_id'));
      }
      
      // obsolete rows from file
      if ( isset($_POST['delete_obsolete']) and !$file_infos['is_new'] )
      {
        foreach ($_LANG as $key => $row)
        {
          // here we skip rows that were in the database, already deleted
          if ( !isset($_LANG_default[$key]) /*and !isset($file_content[$key])*/ )
          {
            $sub_string = is_sub_string($key);
            
            // supposing how the file line should looks like
            switch ($conf['quote'])
            {
              case "'":
                if ($sub_string !== false)
                  $row['search'] = "$".$conf['var_name']."['".str_replace("'","\'",$sub_string[0])."']['".str_replace("'","\'",$sub_string[1])."']";
                else
                  $row['search'] = "$".$conf['var_name']."['".str_replace("'","\'",$key)."']";
                break;
              case '"':
                if ($sub_string !== false)
                  $row['search'] = '$'.$conf['var_name'].'["'.str_replace('"','\"',$sub_string[0]).'"]["'.str_replace('"','\"',$sub_string[1]).'"]';
                else
                  $row['search'] = '$'.$conf['var_name'].'["'.str_replace('"','\"',$key).'"]';
                break;
            }
          
            $i = array_pos($row['search'], $_FILE);
            // if the end of the line is not the end of the row, we search the end into lines bellow
            if ( !preg_match('#(\'|");(\s*)$#', $_FILE[$i]) )
            {
              unset_to_eor($_FILE, $i);
            }
            unset($_FILE[$i]);
          }
        }
      }
      
      $_FILE[] = '?>'; // don't forget to close PHP tag
      
      // try to put the content in the file
      if (!deep_file_put_contents($file_infos['path'], implode($conf['eol'], $_FILE)))
      {
        $file_infos['done_rows'] = array();
        $file_infos['users'] = array();
        array_push($file_infos['errors'], 'Can\'t update/create file\''.$file_infos['path'].'\'');
      }
    }
    
    // try to svn_add the file if it's new
    if ( count($file_infos['done_rows']) > 0 and $conf['svn_activated'] and $file_infos['is_new'] ) 
    {
      $svn_result = svn_add($file_infos['path'], true);
      if ($svn_result['level'] == 'error')
      {
        unlink($file_infos['path']);
        $file_infos['done_rows'] = array();
        array_push($file_infos['errors'], 'svn: '.$svn_result['msg']);
      }
    }
    
    // if the file was successfully modified/created
    if (count($file_infos['done_rows']) > 0)
    {
      array_merge_ref($commit['done_rows'], $file_infos['done_rows']);
      array_merge_ref($commit['users'], $file_infos['users']);
    }
    else
    {
      array_merge_ref($commit['errors'], $file_infos['errors']);
    }
    
    unset($file_infos);
  }
  
  // users
  $commit['users'] = array_unique($commit['users']);
  array_walk($commit['users'], 'print_username');
  
  // everything fine, try to commit
  if ( count($commit['done_rows']) > 0 and $conf['svn_activated'] )
  {
    $svn_result = svn_commit($commit['path'], 
      '['.$commit['project'].'] '.($commit['is_new']?'Add':'Update').' '.$commit['language'].', thanks to : '.implode(' & ', $commit['users'])
      );
    
    // error while commit
    if ($svn_result['level'] == 'error')
    {
      svn_revert($commit['path']);
      $commit['done_rows'] = array();
      array_push($page['errors'], '['.get_project_name($commit['project']).'] '.get_language_name($commit['language']).': '.$svn_result['msg']);
    }
    // commited successfully without files errors
    else if (count($commit['errors']) == 0)
    {
      array_push($page['infos'], '['.get_project_name($commit['project']).'] '.get_language_name($commit['language']).': '.$svn_result['msg']);
    }
  }
  // everything fine, svn not activated
  else if ( count($commit['done_rows']) > 0 and count($commit['errors']) == 0 )
  {
    array_push($page['infos'], '['.get_project_name($commit['project']).'] '.get_language_name($commit['language']).': done');
  }
  // nothing done
  else if (count($commit['done_rows']) == 0)
  {
    array_push($page['errors'], '['.get_project_name($commit['project']).'] '.get_language_name($commit['language']).': failed<br>'.implode('<br>', $commit['errors']));
  }
  
  // some errors in files creation
  if ( count($commit['done_rows']) > 0 and count($commit['errors']) > 0 )
  {
    array_push($page['warnings'], '['.get_project_name($commit['project']).'] '.get_language_name($commit['language']).': partialy commited, see errors bellow<br>'.implode('<br>', $commit['errors']));
  }
  
  // update database
  if (count($commit['done_rows']) > 0)
  {
    // delete rows
    if ($conf['delete_done_rows'])
    {
      $query = '
DELETE FROM '.ROWS_TABLE.'
  WHERE id IN('.implode(',', $commit['done_rows']).')
;';
      mysql_query($query);
    }
    // set rows as done
    else
    {
      $query = '
UPDATE '.ROWS_TABLE.'
  SET status = "done"
  WHERE id IN('.implode(',', $commit['done_rows']).')
;';
      mysql_query($query);

      /*$query = '
UPDATE '.ROWS_TABLE.'
  SET 
    last_edit = NOW()
  WHERE
    id IN('.implode(',', $commit['done_rows']).')
;';
      mysql_query($query);*/
    }
  }
  
  make_stats($commit['project'], $commit['language']);  
  unset($commit);
}

?>