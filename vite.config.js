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


    // build: {
    //     rollupOptions: {
    //       output: {
    //         // заменить глобальные флаги функциональности на литералы boolean
    //         inlineDynamicImports: true,
    //         manualChunks: undefined
    //       }
    //     }
    //   }


});
