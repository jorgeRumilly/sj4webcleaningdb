<div class="row">
    <div class="col-md-6">
        <div class="panel">
            <h3 class="panel-heading">{l s='Accéder aux logs' d='Modules.Sj4webCleaningDb.Admin'}</h3>
            <p>{l s='Utilisez ce lien pour accéder aux logs' d='Modules.Sj4webCleaningDb.Admin'}</p>
            <a href="{$link_to_logs}" class="btn btn-outline-secondary">
                {l s='Voir les logs' d='Modules.Sj4webCleaningDb.Admin'}
            </a>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel">
            <h3 class="panel-heading">{l s='Tâche CRON' d='Modules.Sj4webCleaningDb.Admin'}</h3>
            <p>{l s='Utilisez ce lien dans un CRON si le nettoyage est activé. Cliquez sur le lien pour copier.' d='Modules.Sj4webCleaningDb.Admin'}</p>
            <input type="text"
                   class="form-control"
                   id="sj4web_cron_url"
                   readonly
                   value="{$cron_url}"
                   onclick="copyCronUrl()">
            <small id="sj4web_copy_feedback" class="form-text text-success" style="display:none;">
                ✔️ {l s='Lien copié dans le presse-papier !' d='Modules.Sj4webCleaningDb.Admin'}
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
        console.log('Les dependences:', dependencies);
        const updateDependents = (parent) => {
            const parentName = `enabled_tables_${parent}`;
            const parentOn = document.querySelector(`input[name="${parentName}"][value="1"]`);
            const parentOff = document.querySelector(`input[name="${parentName}"][value="0"]`);
            // console.log('parentName:', parentName, 'ParentOn', parentOn, 'ParentOff', parentOff);

            // console.log('parentName', parentName, 'parent:', parent, 'ParentOn', parentOn, 'ParentOff', parentOff);

            // Vérifier si les éléments parentOn et parentOff existent
            if (!parentOn || !parentOff) return;

            const isEnabled = parentOn.checked;
            console.log('isEnabled', isEnabled);

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
