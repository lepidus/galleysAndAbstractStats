<script>
 $(function() {ldelim}
     $('#galleysAndAbstractStatsSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
 {rdelim});
</script>
<form
 class="pkp_form"
 id="galleysAndAbstractStatsSettings"
 method="POST"
 action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
 {csrf}

 {fbvFormSection label="plugins.generic.galleysAndAbstractStats.footerText"}
     {fbvElement
         type="text"
         id="statsFooterText"
         value=$statsFooterText
         description="plugins.generic.galleysAndAbstractStats.footerText"
     }
 {/fbvFormSection}
 {fbvFormButtons submitText="common.save"}
</form>