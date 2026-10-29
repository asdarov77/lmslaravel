import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
// for vite : laravel to vue baseUrl
import envCompatible from 'vite-plugin-env-compatible';

export default defineConfig({
    plugins: [
        laravel([
            //'resources/css/app.css', // v-select улетает
            'resources/js/app.js',
        ]),

        // for vite : laravel to vue baseUrl
        envCompatible({
            prefix: 'VITE_',
            envFilePath: '.env',
        }),
        //---- end vite : laravel to vue baseUrl
        vue(
            {
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                    compilerOptions: {
                        isCustomElement: (tag) => ['md-linedivider'].includes(tag),
                    },
                },
            }
        ),
    ],

    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },

    server: {
        host: '127.0.0.1',
        watch: {
            // не следить за PHP-зависимостями (экономит лимит inotify-наблюдателей)
            ignored: ['**/vendor/**', '**/storage/**', '**/node_modules/**'],
        },
    },

    test: {
        globals: true, // describe/it/expect без импорта
        environment: 'jsdom',
        include: ['tests/Unit/**/*.spec.js', 'resources/js/**/*.spec.js'],
        exclude: ['tests/E2E/**', 'node_modules/**', 'vendor/**'],
        setupFiles: ['tests/Unit/setup.js'],
        server: {
            deps: {
                // Vuetify тянет .css, которые Node-раннер не умеет импортировать.
                // В тестах CSS не нужен — отдаём пустой модуль.
                inline: [/vuetify/],
            },
        },
        css: false,
    },


    // P1 (аудит производительности): code splitting.
    //
    // Раньше весь app собирался в один entry-чанк: студент, открывающий
    // плеер, скачивал админки (PermissionsManager, UserList), FullCalendar,
    // JSZip и treeselect — первый байр бандла напрямую замедлял TTI плеера.
    // Роуты уже ленивые (`import()` в Router/routes.js), поэтому задача
    // сборки — вынести тяжёлые стабильные библиотеки в отдельные кэшируемые
    // чанки: они меняются редко и переиспользуются между деплоями.
    build: {
        chunkSizeWarningLimit: 800,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) return;

                    if (id.includes('vuetify') || id.includes('@mdi')) return 'vendor-vuetify';
                    if (id.includes('fullcalendar')) return 'vendor-fullcalendar';
                    if (id.includes('vue-i18n') || id.includes('@intlify')) return 'vendor-i18n';
                    if (id.includes('core-js') || id.includes('webfontloader')) return 'vendor-compat';
                    if (/[\\/]node_modules[\\/](vue|@vue|pinia|vuex)[\\/]/.test(id)) return 'vendor-vue';
                    if (id.includes('axios') || id.includes('lodash')) return 'vendor-utils';

                    return 'vendor-misc';
                },
            },
        },
    },
});
