/**
 * Automatically imports all the modules and exports as a single module object
 */
// import * as auth from './auth.store.js';
// import * as auth from './auth.store.js';
// import * as course from './course.store.js';
// import * as ui from './ui.store.js';
// import * as user from './user.store.js';
// import * as userPage from './userPage.store.js';
// const requireModule = require.context('.', false, /\.store\.js$/)
const modules = {}

// requireModule.keys().forEach(filename => {
//   // create the module name from fileName
//   // remove the store.js extension and capitalize
//   const moduleName = filename.replace(/(\.\/|\.store\.js)/g, '').replace(/^\w/, c => c.toUpperCase())

//   modules[moduleName] = requireModule(filename).default || requireModule(filename)
// })
modules['Auth'] = import('./auth.store.js');
modules['User'] = import('./user.store.js');
modules['Course'] = import('./course.store.js');
modules['Ui'] = import('./ui.store.js');
// console.log(modules,"mod");
// modules['Course'] = course;
// modules['Ui'] = ui;
// modules['User'] = user;
// modules['UserPage'] = userPage;

export default modules
