<template>
    <v-container>
        <v-row>
            <v-col cols="3">
                <v-tabs v-model="activeTab" direction="vertical">
                    <v-tab v-for="(setting, index) in settings" :key="index" @wheel="e => changeTab(index)">
                        {{ setting.category }}
                    </v-tab>
                </v-tabs>
            </v-col>
            <v-col cols="9">
                <v-card>
                    <v-card-text>
                        <component :is="currentSettingComponent"></component>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import GradeSettings from './GradeSettings.vue';
import ContentDeliverySettings from './ContentDeliverySettings.vue';
import TutorSettings from './TutorSettings.vue';

export default {
    data() {
        return {
            activeTab: 0,
            settings: [
                { category: this.$t('settings.delivery.tab'), component: ContentDeliverySettings },
                { category: this.$t('settings.tutor.tab'), component: TutorSettings },
                // Наполнения пока нет: компонент существует, вкладка
                // помечена как незавершённая, а не выдаёт себя за рабочую.
                { category: this.$t('settings.grades.tab'), component: GradeSettings },
                // Остальные категории настроек добавляются здесь
                // вместе со своим компонентом.
            ]
        };
    },
    computed: {
        currentSettingComponent() {
            return this.settings[this.activeTab].component;
        }
    },
};
</script>
