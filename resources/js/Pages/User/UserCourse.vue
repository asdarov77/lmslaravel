<template>
  <PageHeader :title="$t('userCourse.title', { name: course.course })" />
  <div class="container-course">
    <div class="card-wrap">
      <v-card
          class="mx-auto learning"
          width="344"
      >
        <v-card-item>
          <div>
            <div class="text-overline mb-1">
              {{$t('userCourse.class')}}: {{ course.className }}
            </div>
            <div class="text-h6 mb-1">
              {{$t('userCourse.category')}}: {{ course.category }}
            </div>
            <div class="text-caption">{{ $t('userCourse.date') }}: {{ localeDate(course.dateStart) }}</div>
          </div>
        </v-card-item>

        <v-card-actions>
          <v-btn variant="outlined" class="btn-action">
            {{ $t('userCourse.passCourse') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </div>
    <div v-for="test in testsCourse" :key="test.id" class="card-wrap">
      <v-card class="mx-auto tests-card" width="344">
        <v-card-item>
          <div>
            <div class="text-h6 mb-1">
              {{$t('userCourse.test')}}: {{ test.title }}
            </div>
            <div class="text-caption">{{ $t('userCourse.date') }}: {{ localeDate(test.dateStart) }}</div>
          </div>
        </v-card-item>

        <v-card-actions>
          <v-btn variant="outlined" class="btn-action">
            {{ $t('userCourse.passTest') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </div>
  </div>
  <section class="course-scores">
    <DataTable
      :title="$t('userCourse.scores')"
      :columns="scoreColumns"
      :rows="testScores"
      :row-key="(row) => row.id"
      :caption="$t('userCourse.scores')"
      :empty-title="$t('userCourse.noTests')"
      :empty-text="$t('userCourse.scoresEmptyText')"
      empty-icon="mdi-clipboard-check-outline"
    />
  </section>

</template>

<script>
import {mapState} from "vuex";
import localeDate from "../../services/dateFormat.service";
import PageHeader from '../../components/ui/PageHeader.vue'
import DataTable from '../../components/ui/DataTable.vue'

export default {
  name: "UserCourse",
  props: {
    id: {
      type: Number,
      required: true
    }
  },
  computed: {
    ...mapState('UserPage', ['course', 'testsCourse', 'testScores'])
  },
  methods: {
    localeDate: localeDate
  },
  data() {
    return {
      scoreColumns: [
        { key: 'id', title: this.$t('userCourse.attempt'), width: '120px' },
        { key: 'titleTest', title: this.$t('userCourse.testName') },
        { key: 'score', title: this.$t('userCourse.score'), width: '120px' },
      ],
    }
  }
}
</script>

<style>
@import "../../../css/app.css";

.btn-lesson {
  background-color: #B0E0E6;
}

.container-course {
  margin: 10px auto !important;
  text-align: center;
}

.btn-action {
  margin: 0 auto;
  background-color: #90EE90;
}

.learning {
  background-color: rgb(226, 256, 255);
}

.learning, .tests-card {
  margin: 10px !important;
  min-height: 200px;
  text-align: left;

}

.tests-card {
  background-color: #c3e6cb;
}

.btn-action {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 20px;
}

.card-wrap {
  display: inline-block
}

.course-scores {
  margin: var(--sp-6) auto;
  max-width: 960px;
}
</style>