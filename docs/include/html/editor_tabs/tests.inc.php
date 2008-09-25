<?php
/****************************************************************/
/* ATutor														*/
/****************************************************************/
/* Copyright (c) 2002-2008 by Greg Gay & Harris Wong			*/
/* Adaptive Technology Resource Centre / University of Toronto  */
/* http://atutor.ca												*/
/*                                                              */
/* This program is free software. You can redistribute it and/or*/
/* modify it under the terms of the GNU General Public License  */
/* as published by the Free Software Foundation.				*/
/****************************************************************/
// $Id: tests.inc.php 7208 2008-01-09 16:07:24Z harris $
if (!defined('AT_INCLUDE_PATH')) { exit; }
?>

<?php
/* Get the list of associated tests with this content on page load */

$_REQUEST['cid'] = intval($_REQUEST['cid']);	//uses request 'cause after 'saved', the cid will become $_GET.
$sql = 'SELECT * FROM '.TABLE_PREFIX."content_tests_assoc WHERE content_id=$_REQUEST[cid]";
$result = mysql_query($sql, $db);
while ($row = mysql_fetch_assoc($result)) {
	$_POST['tid'][] = $row['test_id'];
}

/* get a list of all the tests we have, and links to create, edit, delete, preview */

$sql	= "SELECT *, UNIX_TIMESTAMP(start_date) AS us, UNIX_TIMESTAMP(end_date) AS ue FROM ".TABLE_PREFIX."tests WHERE course_id=$_SESSION[course_id] ORDER BY start_date DESC";
$result	= mysql_query($sql, $db);
$num_tests = mysql_num_rows($result);
?>


<div class="row">
	<p><?php echo _AT('about_content_tests'); ?></p>
</div>


<div class="row">
	<p><?php echo _AT('custom_test_message'); ?></p>
	<textarea name="test_message"><?php echo $_POST['test_message']; ?></textarea>
</div>

<table class="data" summary="" style="width: 90%" rules="cols">
<thead>
<tr>
	<th scope="col">&nbsp;</th>
	<th scope="col"><?php echo _AT('title');          ?></th>
	<th scope="col"><?php echo _AT('status');         ?></th>
	<th scope="col"><?php echo _AT('availability');   ?></th>
	<th scope="col"><?php echo _AT('result_release'); ?></th>
	<th scope="col"><?php echo _AT('submissions');	  ?></th>
	<th scope="col"><?php echo _AT('assigned_to');	  ?></th>
</tr>
</thead>
<tbody>
<?php while ($row = mysql_fetch_assoc($result)) : ?>
<?php
	$checkMe = '';
	if (is_array($_POST['tid']) && in_array($row['test_id'], $_POST['tid'])){
		$checkMe = ' checked="checked"';
	} 
?>
<tr>
	<td><input type="checkbox" name="tid[]" value="<?php echo $row['test_id']; ?>" id="t<?php echo $row['test_id']; ?>" <?php echo $checkMe; ?>/></td>
	<td><label for="t<?php echo $row['test_id']; ?>"><?php echo $row['title']; ?></label></td>
	<td><?php
		if ( ($row['us'] <= time()) && ($row['ue'] >= time() ) ) {
			echo '<em>'._AT('ongoing').'</em>';
		} else if ($row['ue'] < time() ) {
			echo '<em>'._AT('expired').'</em>';
		} else if ($row['us'] > time() ) {
			echo '<em>'._AT('pending').'</em>';
		} ?></td>
	<td><?php $startend_date_format=_AT('startend_date_format'); 

		echo AT_date( $startend_date_format, at_timezone($row['start_date']), AT_DATE_MYSQL_DATETIME). ' ' ._AT('to_2').' ';
		echo AT_date($startend_date_format, at_timezone($row['end_date']), AT_DATE_MYSQL_DATETIME); ?></td>

	<td><?php 
		if ($row['result_release'] == AT_RELEASE_IMMEDIATE) {
			echo _AT('release_immediate');
		} else if ($row['result_release'] == AT_RELEASE_MARKED) {
			echo _AT('release_marked');
		} else if ($row['result_release'] == AT_RELEASE_NEVER) {
			echo _AT('release_never');
		}
	?></td>
	<td><?php
		//get # marked submissions
		$sql_sub = "SELECT COUNT(*) AS sub_cnt FROM ".TABLE_PREFIX."tests_results WHERE status=1 AND test_id=".$row['test_id'];
		$result_sub	= mysql_query($sql_sub, $db);
		$row_sub = mysql_fetch_assoc($result_sub);
		echo $row_sub['sub_cnt'].' '._AT('submissions').', ';

		//get # submissions
		$sql_sub = "SELECT COUNT(*) AS marked_cnt FROM ".TABLE_PREFIX."tests_results WHERE status=1 AND test_id=".$row['test_id']." AND final_score=''";
		$result_sub	= mysql_query($sql_sub, $db);
		$row_sub = mysql_fetch_assoc($result_sub);
		echo $row_sub['marked_cnt'].' '._AT('unmarked');
		?>
	</td>
	<td><?php
		//get assigned groups
		$sql_sub = "SELECT G.title FROM ".TABLE_PREFIX."groups G INNER JOIN ".TABLE_PREFIX."tests_groups T USING (group_id) WHERE T.test_id=".$row['test_id'];
		$result_sub	= mysql_query($sql_sub, $db);
		if (mysql_num_rows($result_sub) == 0) {
			echo _AT('everyone');
		} else {
			$row_sub = mysql_fetch_assoc($result_sub);
			echo $row_sub['title'];
			do {
				echo ', '.$row_sub['title'];
			} while ($row_sub = mysql_fetch_assoc($result_sub));
		}				
		?>
	</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>