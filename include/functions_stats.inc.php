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
 
defined('PATH') or die('Hacking attempt!'); 

/**
 * make stats for a language of a section and register it to DB
 * calculate ratio between the numbers of rows in the default language and the number of existing rows in the current language
 * the value returned is between 0 and 1
 *
 * @param string section
 * @param string language
 * @return float stat
 */
function make_stats($section, $language, $save=true)
{
  global $conf;
  
  // language/section doesn't exist
  if (!file_exists($conf['local_dir'].$section.'/'.$language))
  {
    $stat = 0;
  }
  // language/section exists, will count rows
  else
  {
    $total = $translated = 0;
    $directory = $conf['local_dir'].$section.'/';
    $files = explode(',', $conf['all_sections'][$section]['files']);
    
    foreach ($files as $file)
    {
      // for plain texts
      if (is_plain_file($file))
      {
        // this version count the files
        $_LANG_db = load_language_db($language, $file, $section);
        if ( !empty($_LANG_db) or file_exists($directory.$language.'/'.$file) )
        {
          $translated++;
        }
        $total++;
        
        /* 
        // this version count the lines, not efficient, generate stats over 100%
        $_LANG_default = load_language_file_plain($directory.$conf['default_language'].'/'.$file);
        $_LANG =         load_language_file_plain($directory.$language.'/'.$file);
        $_LANG_db =      load_language_db($language, $file, $section);
      
        $total+= substr_count($_LANG_default['row_value'], $conf['eol']);
        if ($_LANG_db and $_LANG_db[$file]['status'] != 'done')
        {
          $translated+= substr_count($_LANG_db[$file]['row_value'], $conf['eol']);
        }
        else if ($_LANG)
        {
          $translated+= substr_count($_LANG['row_value'], $conf['eol']);
        }
        */
      }
      // for arrays
      else
      {
        $_LANG_default = load_language_file($directory.$conf['default_language'].'/'.$file);
        $_LANG =         load_language_file($directory.$language.'/'.$file);
        $_LANG_db =      load_language_db($language, $file, $section);
        
        $total+= count($_LANG_default);
        foreach ($_LANG_default as $key => $row)
        {
          if ( isset($_LANG[$key]) or isset($_LANG_db[$key]) )
          {
            $translated++;
          }
        }
      }
    }
    
    $stat = ($total != 0) ? min($translated/$total, 1) : 0; // min is to prevent any error during calculation
  }
  
  if ($save)
  {
    $query = '
DELETE FROM '.STATS_TABLE.'
  WHERE
    section = "'.$section.'"
    AND language = "'.$language.'"
;';
    mysql_query($query);
    
    $query = '
INSERT INTO '.STATS_TABLE.'(
    section,
    language,
    date,
    value
  )
  VALUES (
    "'.$section.'",
    "'.$language.'",
    NOW(),
    '.$stat.'
  )
;';
  
    mysql_query($query);
  }
  
  return $stat;
}

/**
 * make stats for a section
 * @param string section
 * @return array of floats stats
 */
function make_section_stats($section, $save=true)
{
  global $conf;
  $stats = array();
  
  foreach (array_keys($conf['all_languages']) as $language)
  {
    $stats[$language] = make_stats($section, $language, $save);
  }
  
  return $stats;
}

/**
 * make stats for a language
 * @param string language
 * @return array of floats stats
 */
function make_language_stats($language, $save=true)
{
  global $conf;
  $stats = array();
  
  foreach (array_keys($conf['all_sections']) as $section)
  {
    $stats[$section] = make_stats($section, $language, $save);
  }
  
  return $stats;
}

/**
 * make all stats
 * @return array of floats stats
 */
function make_full_stats($save=true)
{
  global $conf;
  $stats = array();
  
  foreach (array_keys($conf['all_sections']) as $section)
  {
    $stats[$section] = make_section_stats($section, $save);
  }
  /*foreach (array_keys($conf['all_languages']) as $language)
  {
    $stats[$language] = make_language_stats($language, $save);
  }*/
  
  return $stats;
}

/**
 * get saved stats
 * @param string section
 * @param string language
 * @param string 'language','section','all'
 * @return array
 */
function get_cache_stats($section=null, $language=null, $sum=null)
{
  global $conf;
  
  $where_clauses = array('1=1');
  if (!empty($language))
  {
    $where_clauses[] = 'language = "'.$language.'"';
  }
  if (!empty($section))
  {
    $where_clauses[] = 'section = "'.$section.'"';
  }
  
  $query = '
SELECT * FROM (
  SELECT
      section,
      language,
      value
    FROM '.STATS_TABLE.'
    WHERE
      '.implode("\n      AND ", $where_clauses).'
    ORDER BY date DESC, section ASC, language ASC
  ) as t
  GROUP BY CONCAT(t.section, t.language)
;';

  $result = mysql_query($query);
  $out = array();
  
  while ($row = mysql_fetch_assoc($result))
  {
    $out[ $row['section'] ][ $row['language'] ] = $row['value'];
  }
  
  switch ($sum)
  {
    case 'language':
      $out = reverse_2d_array($out);
      foreach ($out as $language => $row)
      {
        $num = $denom = 0;
        foreach ($row as $section => $value)
        {
          $num+= $value*$conf['all_sections'][ $section ]['rank'];
          $denom+= $conf['all_sections'][ $section ]['rank'];
        }
        $out[ $language ] = $num==0 ? 0 : $num/$denom;
      }
      break;
      
    case 'section':
      foreach ($out as $section => $row)
      {
        $num = $denom = 0;
        foreach ($row as $language => $value)
        {
          $num+= $value*$conf['all_languages'][ $language ]['rank'];
          $denom+= $conf['all_languages'][ $language ]['rank'];
        }
        $out[ $section ] = $num==0 ? 0 : $num/$denom;
      }
      break;
      
    case 'all' :
      foreach ($out as $section => $row)
      {
        $num = $denom = 0;
        foreach ($row as $language => $value)
        {
          $num+= $value*$conf['all_languages'][ $language ]['rank'];
          $denom+= $conf['all_languages'][ $language ]['rank'];
        }
        $out[ $section ] = $num==0 ? 0 : $num/$denom;
      }
      $num = $denom = 0;
      foreach ($out as $section => $value)
      {
        $num+= $value*$conf['all_sections'][ $section ]['rank'];
        $denom+= $conf['all_sections'][ $section ]['rank'];
      }
      $out = $num==0 ? 0 : $num/$denom;
      break;
  }
  
  return $out;
}

/** 
 * generate progression bar
 * @param float value
 * @param int width
 * @return string html
 */
function display_progress_bar($value, $width, $outside = false)
{
  if ($outside) $width-=48;
  return '
  <span class="progressBar '.($value==1?'full':null).'" style="width:'.$width.'px;">
    <span class="bar" style="background-color:'.get_gauge_color($value).';width:'.floor($value*$width).'px;">'.(!$outside?'&nbsp;&nbsp;'.number_format($value*100,2).'%&nbsp;&nbsp;':null).'</span>
  </span>
  '.($outside?'&nbsp;&nbsp;'.number_format($value*100,2).'%':null);
}

/**
 * return a color according to value (gradient is red-orange-green)
 * @param float value
 * @return sring hex color
 */
function get_gauge_color($value)
{
  $gradient = array("F88C8C","F9A88C","FBC58C","FDE28C","FFFF8C","E2FF8C","C5FF8C","A8FF8C","8CFF8C");
  // $gradient = array('ff0000','ff3f00','ff7f00','ffbf00','ffff00','bfff00','7fff00','3fff00','00ff00');
  $index = floor($value*(count($gradient)-1));
  return '#'.$gradient[$index];
}
?>