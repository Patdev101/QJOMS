@if ($paginator->total() > 0)
    <div class="pager">
        <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
        {{ $paginator->links() }}
    </div>
@endif
