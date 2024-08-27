<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Web Care</title>

    <style type="text/css">
        * {
            font-family: Verdana, Arial, sans-serif;
        }

        table {
            font-size: x-small;
        }

        tfoot tr td {
            font-weight: bold;
            font-size: x-small;
        }

        .gray {
            background-color: lightgray
        }
    </style>
</head>
<body>

<table width="100%">
    <tr>
        <td valign="top"><img src="https://via.placeholder.com/150" alt="" width="150"/></td>
        <td align="right">
            <h3>Shiner Electric power company</h3>
            <pre>
                Company representative name
                Company address
                Tax ID
                phone
                fax
            </pre>
        </td>
    </tr>

</table>

<table width="100%">
    <tr>
        <td><strong>From:</strong> Web Care</td>
        <td><strong>To:</strong> {{$data['userFullName']}}</td>
    </tr>

</table>

<br/>

<table width="100%">
    <thead style="background-color: lightgray;">
    <tr>
        <th>Description</th>
        <th>Quantity</th>
        <th>Interval</th>
        <th>Total $</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>{{$data['package_name']}}</td>
        <td align="right">{{$data['interval_count']}}</td>
        <td align="right">{{$data['interval']}}</td>
        <td align="right">{{$data['package_price']}}</td>
    </tr>
    </tbody>

    <tfoot>
    <tr>
        <td colspan="2"></td>
        <td align="right">Subtotal $</td>
        <td align="right">{{$data['package_price']}}</td>
    </tr>
    <tr>
        <td colspan="2"></td>
        <td align="right">Tax $</td>
        <td align="right"></td>
    </tr>
    <tr>
        <td colspan="2"></td>
        <td align="right">Total $</td>
        <td align="right" class="gray">{{$data['package_price']}}</td>
    </tr>
    </tfoot>
</table>

</body>
</html>