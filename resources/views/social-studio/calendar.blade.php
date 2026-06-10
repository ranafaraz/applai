@extends('layouts.app')
@section('title', 'Content Calendar')
@section('page-title', 'Planner')
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Social Studio', 'url' => route('social-studio.dashboard')],
        ['label' => 'Planner'],
    ]" />
@endsection

@push('styles')
<style>
.cal-day { min-height: 80px; }
</style>
@endpush

@section('content')
<div class="p-6 space-y-5" x-data="calendar({{ json_encode($scheduled) }})">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Content Calendar</h1>
        <div class="flex items-center gap-2">
            <button @click="prevMonth()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            {{-- Month selector --}}
            <select x-model="selectedMonth" @change="jumpToMonth()"
                    class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <template x-for="(name, idx) in monthNames" :key="idx">
                    <option :value="idx" x-text="name" :selected="idx === selectedMonth"></option>
                </template>
            </select>

            {{-- Year selector --}}
            <select x-model="selectedYear" @change="jumpToMonth()"
                    class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <template x-for="year in yearRange" :key="year">
                    <option :value="year" x-text="year" :selected="year === selectedYear"></option>
                </template>
            </select>

            <button @click="nextMonth()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        {{-- Day headers --}}
        <div class="grid grid-cols-7 border-b border-slate-200">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
            <div class="text-center text-xs font-semibold text-slate-500 py-2">{{ $day }}</div>
            @endforeach
        </div>
        {{-- Calendar grid --}}
        <div class="grid grid-cols-7">
            <template x-for="(day, idx) in calDays" :key="idx">
                <div class="cal-day border-b border-r border-slate-100 p-1.5"
                     :class="day.isToday ? 'bg-indigo-50' : (day.isCurrentMonth ? 'bg-white' : 'bg-slate-50')">
                    <p class="text-xs font-semibold mb-1"
                       :class="day.isToday ? 'text-indigo-600' : (day.isCurrentMonth ? 'text-slate-700' : 'text-slate-300')"
                       x-text="day.date"></p>
                    <template x-for="post in day.posts" :key="post.id">
                        <a :href="post.url"
                           class="block text-[10px] font-medium px-1 py-0.5 rounded mb-0.5 truncate"
                           :class="{
                               'bg-green-200 text-green-800': post.status === 'published',
                               'bg-violet-200 text-violet-800': post.status === 'scheduled',
                               'bg-red-200 text-red-800': post.status === 'failed',
                               'bg-amber-200 text-amber-800': post.status === 'approved',
                           }"
                           :title="post.title + ' (' + post.status + ', ' + post.tz + ')'"
                           x-text="post.title">
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-4 text-xs">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-violet-200"></span>Scheduled</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-amber-200"></span>Approved</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-green-200"></span>Published</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-200"></span>Failed</span>
    </div>

</div>

@push('scripts')
<script>
function calendar(posts) {
    const now = new Date();
    return {
        today: now,
        selectedYear: now.getFullYear(),
        selectedMonth: now.getMonth(),
        posts,
        monthNames: ['January','February','March','April','May','June','July','August','September','October','November','December'],
        get yearRange() {
            const y = new Date().getFullYear();
            return [y - 1, y, y + 1, y + 2];
        },
        jumpToMonth() {
            // selectedYear/selectedMonth are strings from <select>, coerce to int
            this.selectedYear = parseInt(this.selectedYear);
            this.selectedMonth = parseInt(this.selectedMonth);
        },
        prevMonth() {
            if (this.selectedMonth === 0) { this.selectedMonth = 11; this.selectedYear--; }
            else { this.selectedMonth--; }
        },
        nextMonth() {
            if (this.selectedMonth === 11) { this.selectedMonth = 0; this.selectedYear++; }
            else { this.selectedMonth++; }
        },
        get calDays() {
            const year  = this.selectedYear;
            const month = this.selectedMonth;
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const days = [];

            const todayStr = `${this.today.getFullYear()}-${String(this.today.getMonth()+1).padStart(2,'0')}-${String(this.today.getDate()).padStart(2,'0')}`;

            // Padding before first day
            const prevDays = new Date(year, month, 0).getDate();
            for (let i = firstDay - 1; i >= 0; i--) {
                days.push({ date: prevDays - i, isCurrentMonth: false, isToday: false, posts: [] });
            }

            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                // scheduled_at is already in post's timezone — compare date prefix directly
                const dayPosts = this.posts.filter(p => p.scheduled_at && p.scheduled_at.startsWith(dateStr));
                days.push({ date: d, isCurrentMonth: true, isToday: dateStr === todayStr, posts: dayPosts });
            }

            // Padding after
            const remaining = 42 - days.length;
            for (let d = 1; d <= remaining; d++) {
                days.push({ date: d, isCurrentMonth: false, isToday: false, posts: [] });
            }

            return days;
        }
    };
}
</script>
@endpush
@endsection
