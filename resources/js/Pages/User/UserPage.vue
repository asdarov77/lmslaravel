<template>
    <div>
        <h1 class="h1-title">Добро пожаловать, {{ user.fio }}</h1>

        <v-table class="elevation-1">
            <thead>
                <tr>
                    <th class="text-left">Даты обучения</th>
                    <th class="text-left">Группа</th>
                    <th class="text-left">Курс</th>
                    <th class="text-left">Специальность</th>
                    <th class="text-left">Инструктор</th>
                    <th class="text-left">Тип занятия</th>
                    <th class="text-left">Учебные материалы</th>
                    <th class="text-left">Тесты</th>
                    <th class="text-left">Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="topic in lessons" :key="topic.id">
                    <td>{{ topic.study_from }} - {{ topic.study_to }}</td>
                    <td>{{ getGroupName(topic.group_id) }}</td>
                    <td>{{ getCourseName(topic.course_id) }}</td>
                    <td>{{ getCategoryName(topic.category_id) }}</td>
                    <td>{{ topic.teacher }}</td>
                    <td>{{ topic.typeOfLesson }}</td>

                    <td>
                        <v-btn
                            color="success"
                            flat
                            :to="{
                                name: 'courses.itemmani',
                                query: {
                                    idEdit: topic.course_id,
                                    idCategory: topic.category_id,
                                },
                            }"
                            target="_blank"
                        >
                            Открыть
                        </v-btn>
                    </td>

                    <td>
                        <!-- Если нужно передать aukstructure.id, добавьте его в query -->
                        <v-btn
                            color="success"
                            flat
                            :to="{
                                name: 'questions',
                                query: {
                                    idEdit: topic.course_id,
                                    idCategory: topic.category_id,
                                },
                            }"
                            target="_blank"
                        >
                            Тесты
                        </v-btn>
                    </td>

                    <td align="center">
                        <v-icon
                            small
                            color="green"
                            class="mr-2 cursor-pointer"
                            @click="
                                $router.push({
                                    name: 'question.edit',
                                    params: { idEdit: topic.id },
                                })
                            "
                        >
                            mdi-pencil
                        </v-icon>
                        <v-icon
                            small
                            color="red"
                            class="cursor-pointer"
                            @click="deleteItem(topic.id)"
                        >
                            mdi-delete
                        </v-icon>
                    </td>
                </tr>
            </tbody>
        </v-table>

        <recursive-table :items="items" :is-root="true" />
    </div>
</template>
