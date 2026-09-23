import httpClient from './httpClient'

const fetchCourses = (params = {}) => httpClient.get('/api/course', { params })
const fetchCoursesFilter = (params) => httpClient.get('/api/course', { params })
const fetchCourse = (id) => httpClient.get(`/api/course/${id}`)
// const fetchCourseByAir = (id,) => httpClient.get(`/api/course/`,data)
//-------------------------------------------------------------------
const fetchCourseByCourseAndCategory = (course_id, category_id) => {
  //console.log(course_id,"courseId");
  //console.log(category_id,"categoryId");
  return httpClient.get(`/api/course?course_id=${course_id}&category_id=${category_id}`);
};
const fetchCourseByCourse = (course_id) => {
  return httpClient.get(`/api/course?course_id=${course_id}`);
};

const fetchCourseByCategory = (category_id) => {
  return httpClient.get(`/api/course?category_id=${category_id}`);
};

//-------------------------------------------------------------------
const fetchCoursecat = (id) => httpClient.get(`/api/courses/cat/${id}`)
const updateCourse = (id, data) => httpClient.patch(`/api/course/${id}`, data)
const createCourse = data => httpClient.post('/api/course', data)
const deleteCourse = id => httpClient.delete(`/api/course/${id}`)
// FIX !!!  fetchPermissions,

const fetchCategories = () => httpClient.get('/api/categories')
const fetchCategory = (id) => httpClient.get(`/api/categories/${id}`)
//const fetchPermissions = params => httpClient.get('/users/v1/api-permissions/', { params: params })
const createCategory = data => httpClient.post('/api/categories', data)
const updateCategory = (id, data) => httpClient.put(`/api/categories/${id}`, data)
const deleteCategory = id => httpClient.delete(`/api/categories/${id}`)
// классы
//const fetchClasses = () => httpClient.get('/api/classesfs/')
const fetchAircrafts = () => httpClient.get('/api/classes')
const fetchAircraft = (id) => httpClient.get(`/api/classes/${id}`)
const createAircraft = data => httpClient.post('/api/classes', data)
const updateAircraft = (id, data) => httpClient.put(`/api/classes/${id}`, data)
const deleteAircraft = id => httpClient.delete(`/api/classes/${id}`)
// AukStructure
const fetchGroup2learnings = (data) => httpClient.post(`/api/group/learning`,data)

const deleteGroup2learnings = (id) =>httpClient.delete(`api/learning/${id}`)
//const fetchLearnings = () =>httpClient.get('api/learning' ) еще не сделал
export {
  fetchCourses,
  fetchCoursesFilter,
  fetchCourse, // удалить потом
  fetchCoursecat,// удалить потом
  //-----------------------------------
  fetchCourseByCourseAndCategory,
  fetchCourseByCourse,
  fetchCourseByCategory,
//-------------------------------------
  updateCourse,
  createCourse,
  deleteCourse,
  fetchCategories,
  fetchCategory,
  //fetchPermissions,  
  createCategory,
  updateCategory,
  deleteCategory,
  // классы
  //fetchClasses,
  fetchAircraft,
  fetchAircrafts,
  createAircraft,
  updateAircraft,
  deleteAircraft,
  // group2learning
  fetchGroup2learnings,
  deleteGroup2learnings,
  // /fetchLearnings, // еще не сделал,получение всех занятий
}
