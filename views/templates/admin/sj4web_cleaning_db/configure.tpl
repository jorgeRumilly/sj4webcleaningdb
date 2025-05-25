<div class="row">
    <div class="col-md-6">
        <div class="panel">
            <h3 class="panel-heading">{l s='Access logs' d='Modules.Sj4webcleaningdb.Admin'}</h3>
            <p>{l s='Use this link to access the logs' d='Modules.Sj4webcleaningdb.Admin'}</p>
            <a href="{$link_to_logs}" class="btn btn-outline-secondary">
                {l s='View logs' d='Modules.Sj4webcleaningdb.Admin'}
            </a>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel">
            <h3 class="panel-heading">{l s='CRON task' d='Modules.Sj4webcleaningdb.Admin'}</h3>
            <p>{l s='Use this link in a CRON if cleanup is enabled. Click the link to copy it.' d='Modules.Sj4webcleaningdb.Admin'}</p>
            <input type="text"
                   class="form-control"
                   id="sj4web_cron_url"
                   readonly
                   value="{$cron_url}"
                   onclick="copyCronUrl()">
            <small id="sj4web_copy_feedback" class="form-text text-success" style="display:none;">
                ✔️ {l s='Link copied to clipboard!' d='Modules.Sj4webcleaningdb.Admin'}
            </small>
        </div>
    </div>
</div>
{literal}
<script>
    function copyCronUrl() {
        var input = document.getElementById('sj4web_cron_url');
        var feedback = document.getElementById('sj4web_copy_feedback');
        input.select();
        document.execCommand('copy');
        feedback.style.display = 'inline';
        setTimeout(function() {
            feedback.style.display = 'none';
        }, 2000);
    }
    document.addEventListener('DOMContentLoaded', () => {
        const dependencies = {/literal}{$table_dependencies|@json_encode nofilter}{literal};
        const updateDependents = (parent) => {
            const parentName = `enabled_tables_${parent}`;
            const parentOn = document.querySelector(`input[name="${parentName}"][value="1"]`);
            const parentOff = document.querySelector(`input[name="${parentName}"][value="0"]`);
            // console.log('parentName:', parentName, 'ParentOn', parentOn, 'ParentOff', parentOff);

            // console.log('parentName', parentName, 'parent:', parent, 'ParentOn', parentOn, 'ParentOff', parentOff);

            // Vérifier si les éléments parentOn et parentOff existent
            if (!parentOn || !parentOff) return;

            const isEnabled = parentOn.checked;

            dependencies[parent].forEach(child => {
                const childName = `enabled_tables_${child}`;
                const childOn = document.querySelector(`input[name="${childName}"][value="1"]`);
                const childOff = document.querySelector(`input[name="${childName}"][value="0"]`);
                // console.log('childName:', childName, 'ChildOn', childOn, 'ChildOff', childOff);
                if (!childOn || !childOff) return;

                if (isEnabled) {
                    // Parent activé → dépendant forcé à OUI et grisé
                    childOn.checked = true;
                    childOff.checked = false;
                    childOn.disabled = true;
                    childOff.disabled = true;
                    // syncHiddenField(childName, true);
                } else {
                    // Parent désactivé → dépendant modifiable
                    childOn.disabled = false;
                    childOff.disabled = false;
                }

            });
        };

        Object.keys(dependencies).forEach(parent => {
            const on = document.querySelector(`input[name="enabled_tables_${parent}"][value="1"]`);
            const off = document.querySelector(`input[name="enabled_tables_${parent}"][value="0"]`);

            if (on) on.addEventListener('change', () => updateDependents(parent));
            if (off) off.addEventListener('change', () => updateDependents(parent));

            // Initialiser dès le chargement
            updateDependents(parent);
        });
    });
</script>
{/literal}
