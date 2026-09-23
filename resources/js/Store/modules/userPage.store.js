const initialState = () => ({
    namespaced: true,
    courses: [
        {
            courseId: 1,
            className: 'МИ-28',
            category: 'Инженер',
            courseName: 'Подготовка к использованию комплекса',
            topics: [
                {
                    topicId: 1,
                    topicName: "Подготовка к использованию ВМ-В",
                    chapters: [
                        {
                            chapterId: 1,
                            chapterName: "Подготовка к полету ВМ-В",
                            countHours: 16,
                            score: 5,
                            correctAnswers: 93,
                            progress: 100,
                            dateChapterStart: new Date(2018,1,26, 16),
                            dateChapterStop: new Date(2018,2,26, 16),
                        },
                        {
                            chapterId: 2,
                            chapterName: "Самолетные системы",
                            countHours: 9,
                            score: 4,
                            correctAnswers: 73,
                            progress: 89,
                            dateChapterStart: new Date(2018,2,26, 16),
                            dateChapterStop: new Date(2018,3,26, 16),
                        },
                    ]
                },
                {
                    topicId: 2,
                    topicName: "Подготовка к использованию ВМ-БЛА",
                    chapters: [
                        {
                            chapterId: 3,
                            chapterName: "Подготовка к полету ВМ-БЛА",
                            countHours: 1,
                            score: '',
                            correctAnswers: '',
                            progress: '',
                            dateChapterStart: new Date(2018,3,26, 16),
                            dateChapterStop: new Date(2018,4,26, 16),
                        },
                        {
                            chapterId: 4,
                            chapterName: "Действия расчета комплекса при запуске двигателя ВМ-БЛА",
                            countHours: 1,
                            score: '',
                            correctAnswers: '',
                            progress: '',
                            dateChapterStart: new Date(2018,4,26, 16),
                            dateChapterStop: new Date(2018,5,26, 16),
                        },
                    ]
                },
                ],
            dateStart: new Date(2018,1,26, 16),
            dateStop: new Date(2018,2,26, 16),
        },
        {
            courseId: 2,
            className: 'МИ-8',
            category: 'Борт радист',
            courseName: 'Описание воздушной мишени-имитатора БЛА (ВМ-БЛА)',
            topics: [
                {
                    topicId: 3,
                    topicName: "Основные сведения",
                    chapters: [
                        {
                            chapterId: 5,
                            chapterName: "Основные сведения о воздушной мишени",
                            countHours: 8,
                            score: '',
                            correctAnswers: '',
                            progress: '',
                            dateChapterStart: new Date(2018,5,26, 16),
                            dateChapterStop: new Date(2018,6,26, 16),
                        },
                    ]
                },
                {
                    topicId: 4,
                    topicName: "Системы воздушной мишени - имитатора БЛА",
                    chapters: [
                        {
                            chapterId: 6,
                            chapterName: "Силовая установка",
                            countHours: 2,
                            score: 3,
                            correctAnswers: 60,
                            progress: 55,
                            dateChapterStart: new Date(2018,3,26, 16),
                            dateChapterStop: new Date(2018,4,26, 16),
                        },
                        {
                            chapterId: 7,
                            chapterName: "Действия расчета комплекса при запуске двигателя ВМ-БЛА",
                            countHours: 2,
                            score: 2,
                            correctAnswers: 20,
                            progress: 25,
                            dateChapterStart: new Date(2018,4,26, 16),
                            dateChapterStop: new Date(2018,5,26, 16),
                        },
                    ]
                },
            ],
            dateStart:  new Date(2023,2,5, 16),
            dateStop: new Date(2023,3,26, 16),
        },
    ],
    course:  {
        courseId: 1,
        className: 'МИ-28',
        category: 'Инженер',
        course: 'Конструкция силовой установки',
        dateStart: new Date(2018,1,26, 16),
    },
    testsCourse: [
        {
            id: 1,
            title: 'Название теста',
            typeTest: 'Тип теста',
            dateStart:  new Date(2023,3,15, 10, 15),

        },
        {
            id: 2,
            title: 'Название теста',
            typeTest: 'Тип теста',
            dateStart:  new Date(2023,3,15, 10, 15),

        },
        {
            id: 3,
            title: 'Название теста',
            typeTest: 'Тип теста',
            dateStart:  new Date(2023,3,15, 10, 15),

        },
        {
            id: 4,
            title: 'Название теста',
            typeTest: 'Тип теста',
            dateStart:  new Date(2023,3,15, 10, 15),

        },
        {
            id: 5,
            title: 'Название теста',
            typeTest: 'Тип теста',
            dateStart:  new Date(2023,3,15, 10, 15),

        },
        {
            id: 6,
            title: 'Название теста',
            typeTest: 'Тип теста',
            dateStart:  new Date(2023,3,15, 10, 15),

        },        {
            id: 7,
            title: 'Название теста',
            typeTest: 'Тип теста',
            dateStart:  new Date(2023,3,15, 10, 15),

        },
    ],
    testScores: [{
        id: 1,
        titleTest: 'Тест на знание вертолета',
        score: 'не сдал'
    }, {
            id: 2,
            titleTest: 'Тест на знание материала',
            score: 'сдал'
        },
    ]


})

const state = initialState()

//export default UserPageModule;
//export default {
    export default {
    namespaced: true,
    state,
    // getters,
    // actions,
    // mutations
}
