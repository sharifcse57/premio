import axios from 'axios';
import Toasted from 'vue-toasted';
Vue.use(Toasted);

new Vue({
    el: '#app',
    data: {
        formData: {
            rules: [
                { show: 'show', type: 'contains', value: '' }
            ],
            alertText: ''
        },
        domain: 'www.domain.com/',
        jsSnippet: '',
        uniqueId: '',
        errors: []
    },
    mounted() {
        this.fetchRules();
    },
    methods: {
        addRule() {
            this.formData.rules.push({ show: 'show', type: 'contains', value: '' });
        },
        deleteRule(ruleId, index) {
            if (ruleId) {
                axios.delete(`/rules/${ruleId}`)
                    .then(response => {
                        this.formData.rules = this.formData.rules.filter(rule => rule.id !== ruleId);
                    })
                    .catch(error => {
                        console.error('Error deleting rule:', error);
                    });
            } else {
                this.formData.rules.splice(index, 1);
            }

        },
        saveRules() {
            axios.post('/rules/store', { rules: this.formData.rules, alertText: this.formData.alertText })
                .then(response => {
                    this.showToast();
                    this.fetchRules();
                    this.errors = [];
                })
                .catch(error => {
                    console.error('Error saving rules:', error);

                    if (error.response.status === 422) {
                        this.errors = error.response.data.errors;
                    }
                });
        },
        fetchRules() {
            axios.get('/rules')
                .then(response => {
                    this.formData.rules = response.data.rules;
                    this.uniqueId = response.data.client_id;
                    this.formData.alertText = response.data.alertInfo?.text;
                    if (!response.data.rules.length) {
                        this.addRule();
                    } else {
                        this.setSnippet();
                    }
                })
                .catch(error => {
                    console.error('Error fetching rules:', error);
                });
        },
        setSnippet() {
            this.jsSnippet = `<script src="https://dev-pharmalys.slsweb.ch/generate-script/${this.uniqueId}"></script>`;
        },
        getError(key) {
            return this.errors[key] ? this.errors[key][0] : '';
        },
        showToast() {
            this.$toasted.show('Item saved successfully!', {
                position: 'top-right',
                theme: 'toasted-primary',
                duration: 2000
            });
        }
    }
});
