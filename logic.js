import { questions } from './questions.js';
const { createApp, ref, computed, reactive } = Vue;

createApp({
  setup() {
    const currentIndex = ref(0);
    const score = ref(0);
    const feedback = ref(null);
    const userAnswers = reactive({});

    const currentQuestion = computed(() => questions[currentIndex.value]);
    const currentComponent = computed(() => {
      switch(currentQuestion.value?.type) {
        case 'match': return 'match-question';
        case 'choice': return 'choice-question';
        case 'sort': return 'sort-question';
      }
      return 'div';
    });

    function onAnswered(result) {
      feedback.value = result;
      if (result.correct) score.value++;
      userAnswers[currentIndex.value] = result.answer;
    }

    function next() {
      feedback.value = null;
      currentIndex.value++;
    }

    function formatAnswer(a) {
      return Array.isArray(a) ? a.join(', ') : a;
    }

    return { questions, currentIndex, score, feedback, currentQuestion, currentComponent, next, onAnswered, formatAnswer, userAnswers };
  }
})
.component('choice-question', {
  props: ['question'],
  setup(props, { emit }) {
    const selected = ref(null);
    function select(idx) {
      if (selected.value !== null) return;
      selected.value = idx;
      const correct = idx === props.question.correct;
      emit('answered', { correct, answer: props.question.options[idx] });
    }
    function btnClass(idx) {
      if (selected.value === null) return 'border';
      if (idx === props.question.correct) return 'bg-green-200 border';
      if (idx === selected.value) return 'bg-red-200 border';
      return 'border';
    }
    return { selected, select, btnClass };
  },
  template: `
    <div>
      <h2 class="text-lg font-semibold mb-4">{{ question.question }}</h2>
      <button v-for="(opt, idx) in question.options" :key="idx" @click="select(idx)" :class="btnClass(idx) + ' w-full text-left p-2 mb-2 rounded'">
        {{ opt }}
      </button>
    </div>
  `
})
.component('sort-question', {
  components: { draggable: vuedraggable },
  props: ['question'],
  setup(props, { emit }) {
    const items = ref([...props.question.items]);
    const checked = ref(false);
    const correct = ref(false);
    function check() {
      checked.value = true;
      const currentOrder = items.value.map(i => props.question.items.indexOf(i));
      correct.value = JSON.stringify(currentOrder) === JSON.stringify(props.question.correctOrder);
      emit('answered', { correct: correct.value, answer: items.value });
    }
    return { items, check, checked, correct };
  },
  template: `
    <div>
      <h2 class="text-lg font-semibold mb-4">{{ question.question }}</h2>
      <draggable v-model="items" item-key="text" ghost-class="opacity-50" class="bg-gray-100 p-2 rounded">
        <template #item="{element}">
          <div class="p-2 mb-2 bg-white border rounded cursor-move">{{ element }}</div>
        </template>
      </draggable>
      <button @click="check" class="mt-4 px-4 py-2 bg-teal-500 text-white rounded">Antwort prüfen</button>
      <p v-if="checked" class="mt-2 font-semibold" :class="correct ? 'text-green-600' : 'text-red-600'">{{ correct ? 'Richtig!' : 'Leider falsch.' }}</p>
    </div>
  `
})
.component('match-question', {
  components: { draggable: vuedraggable },
  props: ['question'],
  setup(props, { emit }) {
    const pool = ref(props.question.pairs.map(p => p.term));
    const answers = ref(props.question.pairs.map(() => []));
    const checked = ref(false);
    const correct = ref(false);
    function check() {
      checked.value = true;
      correct.value = answers.value.every((arr, idx) => arr[0] === props.question.pairs[idx].term);
      const ans = answers.value.map(a => a[0] || '');
      emit('answered', { correct: correct.value, answer: ans });
    }
    return { pool, answers, check, checked, correct };
  },
  template: `
    <div>
      <h2 class="text-lg font-semibold mb-4">{{ question.question }}</h2>
      <div class="flex flex-col md:flex-row gap-4">
        <div class="md:w-1/3">
          <p class="font-semibold mb-2">Begriffe</p>
          <draggable v-model="pool" group="items" class="min-h-[50px] p-2 bg-gray-100 rounded">
            <template #item="{element}">
              <div class="p-2 m-1 bg-teal-200 rounded cursor-move">{{ element }}</div>
            </template>
          </draggable>
        </div>
        <div class="flex-1">
          <p class="font-semibold mb-2">Definitionen</p>
          <div v-for="(pair, idx) in question.pairs" :key="idx" class="mb-4">
            <div class="p-2 bg-gray-200 rounded mb-2">{{ pair.definition }}</div>
            <draggable v-model="answers[idx]" group="items" :animation="150" class="min-h-[40px] p-2 border rounded">
              <template #item="{element}">
                <div class="p-2 bg-teal-200 rounded cursor-move">{{ element }}</div>
              </template>
            </draggable>
          </div>
        </div>
      </div>
      <button @click="check" class="mt-4 px-4 py-2 bg-teal-500 text-white rounded">Antwort prüfen</button>
      <p v-if="checked" class="mt-2 font-semibold" :class="correct ? 'text-green-600' : 'text-red-600'">{{ correct ? 'Richtig!' : 'Leider falsch.' }}</p>
    </div>
  `
})
.mount('#app');
