<?php $page_title = 'Error message'; ?>
<?php require dirname(__FILE__) . "/layout/head.php"; ?>

    <style>
        body{
            padding-top:100px;
        }
        h3{
            border-bottom: #e0e0e0  solid 1px;
            padding-bottom: 30px;
        }
        h3{
            border: none;
        }
        svg{
            margin-right:10px;
            color:grey;
        }
        a{
            margin-top:20px;
            margin-bottom:20px;
        }
        #header{
            padding:30px;
            font-size: 40px;
            color:grey;
        }
        #suggestions{
            margin-bottom: 10px;
        }
    </style>

    <div class="container">
        <div class="card">
            <p class="card-header" id="header">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <b>Error</b>
            </p>
            <div class="card-body">
                <h2 class="card-title">Tiki Manager is not configured</h2>
                <p id="suggestions">Suggestions:</p>
                <ul>
                    <li>Tiki-Manager is missing vendor/autoload.php.<br/>Please run tiki-manager from command line or run composer install.</li>
                </ul>
                <a href="https://doc.tiki.org/Manager#Installation" class="btn btn-primary btn-lg">Read More</a>
            </div>
        </div>
    </div>

<?php require dirname(__FILE__) . "/layout/footer.php"; ?>
