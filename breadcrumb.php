<?php
// Child theme override of RetroTube breadcrumb using Rank Math
if (function_exists('rank_math_the_breadcrumbs')) {
  echo '<div id="breadcrumbs">';
  rank_math_the_breadcrumbs();
  echo '</div>';
}
