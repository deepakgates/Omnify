<table>
    <thead>
    <th>
        Event
    </th>
    </thead>
<tbody>
<?php
foreach ($calendars as $calendar){
    echo '<tr><td>'.$calendar->title.'</td></tr>';
}
?>
</tbody>
</table>