@include('includes.head')

<body>
    <div class="loader"></div>
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            @include('includes.right_top_nav')
            @include('includes.side_nav')

            <!-- Main Content -->
            <div class="main-content pt-5 mt-5">
                <section class="section mb-5 pb-1 px-0">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Calendar</h4>
                            </div>
                            <div class="card-body">
                                <div class="fc-overflow">
                                    <div id="myEvent"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @include('includes.footer')

    <!-- Bootstrap Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1" role="dialog" aria-labelledby="eventDetailsLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventDetailsLabel">Test Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><strong>Test:</strong> <span id="modalTestTitle"></span></p>
                    <p><strong>Date:</strong> <span id="modalTestDate"></span></p>
                    <p><strong>Description:</strong></p>
                    <p id="modalTestDescription"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- FullCalendar JS -->
    <script src="{{ asset('bundles/fullcalendar/fullcalendar.min.js') }}"></script>

    <script>
        $(document).ready(function () {
            $('#myEvent').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                events: "{{ route('calendar.events') }}", // Pull events dynamically from the route
                eventClick: function (event) {
                    // Format the date to 'j F Y' (e.g., 10 May 2025)
                    var formattedDate = moment(event.start).format('D MMMM YYYY');
                    
                    // Set the modal's content based on the event clicked
                    $('#modalTestTitle').text(event.title);
                    $('#modalTestDate').text(formattedDate); // Display the formatted date
                    $('#modalTestDescription').html(event.description.replace(/\n/g, '<br>')); // Handle line breaks
                    // Show the modal
                    $('#eventDetailsModal').modal('show');
                }
            });
        });
    </script>
</body>
