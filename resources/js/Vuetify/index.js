import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import colors from 'vuetify/lib/util/colors'
import { createVuetify } from 'vuetify';
import { md3 } from 'vuetify/blueprints'   // работает ли ?
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

const vuetify = createVuetify({
    components,
    directives,
    theme: {
        themes: {
            light: {
                dark: false,
                colors: {
                    primary: colors.blue.darken2, // #E53935
                    secondary: colors.blue.lighten4, // #FFCDD2
                    // ...
                }
            },
        },
    },
})

export default vuetify;
