{{-- kerangka pemuatan, dipakai JS sebagai <template> dan bisa di-include langsung --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-hidden="true">
  @for($i = 0; $i < 6; $i++)
    <div class="bx-card" style="--i: {{ $i }}">
      <div class="flex items-start gap-4">
        <div class="bx-skel h-12 w-[3.9rem] !rounded-xl"></div>
        <div class="flex-1 space-y-2.5 pt-1">
          <div class="bx-skel h-4 w-4/5"></div>
          <div class="bx-skel h-3.5 w-1/2"></div>
        </div>
      </div>
      <div class="flex gap-2">
        <div class="bx-skel h-6 w-16 !rounded-full"></div>
        <div class="bx-skel h-6 w-24 !rounded-full"></div>
        <div class="bx-skel h-6 w-20 !rounded-full"></div>
      </div>
    </div>
  @endfor
</div>
