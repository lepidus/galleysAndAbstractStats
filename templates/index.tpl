<div class="item views">
  <div class="label">{translate key="plugins.generic.galleysAndAbstractStats.index.label"}</div>
  <div class="value">
    <ul style="list-style: none; padding: 0; margin: 0;">
      <li style="padding: 5px 0;">{translate key="article.abstract"} <span id="viewsCount">{$abstractViews}</span></li>
      {if count($galleysViews) > 0}
        {foreach from=$galleysViews item=galley}
          <li style="padding: 5px 0;">{$galley['galleyLabel']|escape} <span class="task_count" id="viewsCount">{$galley['galleyViews']|escape}<span></li>
        {/foreach}
      {/if}
    </ul>
    <sub>{$statsFooterText}</sub>
  </div>
</div>