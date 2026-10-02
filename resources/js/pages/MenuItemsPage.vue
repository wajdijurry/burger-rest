<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { createMenuItem, listIngredients, listMenuItems, type LineInput } from '@/api/resources';
import { errorMessage, fieldErrors, generalErrorMessage } from '@/lib/errors';
import type { Ingredient, MenuItem } from '@/types';

const menuItems = ref<MenuItem[]>([]);
const ingredients = ref<Ingredient[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);

const name = ref('');
const lines = reactive<LineInput[]>([{ ingredient_id: 0, quantity: '' }]);
const submitting = ref(false);
const submitError = ref<string | null>(null);
const fieldErrs = ref<Record<string, string[]>>({});

function addLine() {
    lines.push({ ingredient_id: 0, quantity: '' });
}

function removeLine(index: number) {
    if (lines.length > 1) lines.splice(index, 1);
}

async function load() {
    loading.value = true;
    try {
        const [items, ings] = await Promise.all([listMenuItems(), listIngredients()]);
        menuItems.value = items;
        ingredients.value = ings;
        loadError.value = null;
    } catch (e) {
        loadError.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    name.value = '';
    lines.splice(0, lines.length, { ingredient_id: 0, quantity: '' });
}

async function submit() {
    if (submitting.value) return;
    submitting.value = true;
    submitError.value = null;
    fieldErrs.value = {};
    try {
        const payload = lines.map((l) => ({ ingredient_id: Number(l.ingredient_id), quantity: l.quantity.trim() }));
        const created = await createMenuItem(name.value.trim(), payload);
        menuItems.value = [...menuItems.value, created].sort((a, b) => a.name.localeCompare(b.name));
        resetForm();
    } catch (e) {
        fieldErrs.value = fieldErrors(e);
        submitError.value = generalErrorMessage(e);
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-xl font-semibold text-slate-800">Menu Items</h1>

        <form class="space-y-3 rounded border border-slate-200 bg-white p-4" @submit.prevent="submit">
            <div>
                <label for="mi-name" class="block text-xs font-medium text-slate-600">Name</label>
                <input
                    id="mi-name"
                    v-model="name"
                    required
                    maxlength="160"
                    placeholder="Classic Burger"
                    class="mt-1 rounded border border-slate-300 px-2 py-1 text-sm"
                />
            </div>

            <div>
                <p class="mb-1 text-xs font-medium text-slate-600">Recipe lines</p>
                <div v-for="(line, i) in lines" :key="i" class="mb-2 flex items-end gap-2">
                    <select v-model.number="line.ingredient_id" required class="rounded border border-slate-300 px-2 py-1 text-sm">
                        <option :value="0" disabled>Select ingredient…</option>
                        <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                            {{ ing.name }} ({{ ing.unit }})
                        </option>
                    </select>
                    <input
                        v-model="line.quantity"
                        required
                        placeholder="Quantity"
                        class="w-32 rounded border border-slate-300 px-2 py-1 text-sm"
                    />
                    <button
                        type="button"
                        class="rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100"
                        :disabled="lines.length <= 1"
                        @click="removeLine(i)"
                    >
                        Remove
                    </button>
                </div>
                <button type="button" class="text-sm text-sky-600 hover:underline" @click="addLine">+ Add line</button>
            </div>

            <button
                type="submit"
                :disabled="submitting || ingredients.length === 0"
                class="rounded bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50"
            >
                {{ submitting ? 'Saving…' : 'Create menu item' }}
            </button>
            <p v-if="ingredients.length === 0" class="text-xs text-slate-400">
                Add at least one ingredient first.
            </p>
            <p v-if="submitError" role="alert" class="text-sm text-red-600">{{ submitError }}</p>
            <p v-for="(msgs, field) in fieldErrs" :key="field" class="text-xs text-red-500">
                {{ field }}: {{ msgs.join(', ') }}
            </p>
        </form>

        <p v-if="loadError" role="alert" class="text-sm text-red-600">{{ loadError }}</p>

        <div class="space-y-3">
            <p v-if="!loading && menuItems.length === 0" class="text-sm text-slate-400">No menu items yet.</p>
            <div v-for="item in menuItems" :key="item.id" class="rounded border border-slate-200 bg-white p-4">
                <h3 class="font-medium text-slate-800">{{ item.name }}</h3>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    <li v-for="rl in item.recipe_lines" :key="rl.id">
                        {{ rl.quantity }} {{ rl.unit }} — {{ rl.ingredient_name }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
