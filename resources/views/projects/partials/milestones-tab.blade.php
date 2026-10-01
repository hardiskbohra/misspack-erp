<label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label>
                            <input class="master-input" type="number" name="sort_order" id="edit_sort_order" min="0">
                        </div>

                        <label class="master-check"><input type="checkbox" name="is_public" id="edit_is_public"
                                value="1"></label>
                        <label class="master-check"><input type="checkbox" name="is_required" id="edit_is_required"
                                value="1"></label><label</label><label</label><label</label><label</label>
                            <textarea class="master-textarea" name="blocked_reason" id="edit_blocked_reason" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="pmile-modal-footer">
                    <button type="button" class="master-btn master-btn-soft" data-close-milestone-modal>Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Save Milestone</button>
                </div>
            </form>
            <div>
                <form method="POST" action="#" id="deleteMilestoneForm" class="pmile-modal-delete-form"
                    onsubmit="return confirm('Delete this milestone?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="pd-link-danger"><i class="fa-solid fa-trash"></i> Delete Milestone</button>
                </form>
            </div>
        </div>
    </div>
</section>


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/projects.css') }}">
@endpush
